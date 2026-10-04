#!/usr/bin/env python3
"""Generate standard Android/iOS runners using the locally installed Flutter SDK."""
import pathlib
import plistlib
import re
import shutil
import subprocess
import sys

root = pathlib.Path(__file__).resolve().parents[1]
app = root / 'flutter'
if not shutil.which('flutter'):
    sys.exit('Installez Flutter stable, puis relancez : python3 scripts/prepare_flutter.py')
# Preserve application code, tests and analysis settings during runner generation.
paths = [app / 'pubspec.yaml', app / 'analysis_options.yaml', *sorted((app / 'lib').rglob('*.dart')), *sorted((app / 'test').rglob('*.dart'))]
source = {p.relative_to(app): p.read_bytes() for p in paths if p.is_file()}
try:
    subprocess.run(['flutter', 'create', '--platforms=android,ios', '--org', 'com.deardevice', '--project-name', 'wbenglish_mobile', '--no-pub', '.'], cwd=app, check=True)
finally:
    for path, data in source.items():
        (app / path).parent.mkdir(parents=True, exist_ok=True)
        (app / path).write_bytes(data)
# Delete only the generated counter test, which is unrelated to this app.
test = app / 'test' / 'widget_test.dart'
if test.exists() and 'Counter increments smoke test' in test.read_text():
    test.unlink()
manifest = app / 'android/app/src/main/AndroidManifest.xml'
text = manifest.read_text()
if 'android.permission.INTERNET' not in text:
    index = text.index('>', text.index('<manifest')) + 1
    text = text[:index] + '\n    <uses-permission android:name="android.permission.INTERNET"/>\n' + text[index:]
text = text.replace('android:label="wbenglish_mobile"', 'android:label="WB English"')
if 'android.permission.RECORD_AUDIO' not in text:
    index = text.index('>', text.index('<manifest')) + 1
    text = text[:index] + '\n    <uses-permission android:name="android.permission.RECORD_AUDIO"/>\n' + text[index:]
manifest.write_text(text)
# The audio/path providers require Android 24+; request microphone at runtime.
for gradle in [app / 'android/app/build.gradle.kts', app / 'android/app/build.gradle']:
    if gradle.exists():
        config = gradle.read_text()
        config = re.sub(r'minSdk\s*=\s*flutter.minSdkVersion', 'minSdk = 24', config)
        config = re.sub(r'minSdkVersion\s+flutter.minSdkVersion', 'minSdkVersion 24', config)
        gradle.write_text(config)
info = app / 'ios/Runner/Info.plist'
with info.open('rb') as f:
    config = plistlib.load(f)
config['NSMicrophoneUsageDescription'] = 'Enregistrer vos phrases pour les envoyer à votre formateur WB English.'
with info.open('wb') as f:
    plistlib.dump(config, f, sort_keys=False)
project = app / 'ios/Runner.xcodeproj/project.pbxproj'
if project.exists():
    config = project.read_text()
    config = re.sub(r'IPHONEOS_DEPLOYMENT_TARGET = ([0-9.]+);', lambda m: 'IPHONEOS_DEPLOYMENT_TARGET = ' + (m.group(1) if float(m.group(1)) >= 13 else '13.0') + ';', config)
    project.write_text(config)

subprocess.run(['flutter', 'pub', 'get'], cwd=app, check=True)
subprocess.run(['dart', 'format', 'lib'], cwd=app, check=True)
subprocess.run(['flutter', 'analyze'], cwd=app, check=True)
print('Sources préparées et analysées. Dans flutter/ : flutter run')
