#!/usr/bin/env python3
"""Generate standard Android/iOS runners using the locally installed Flutter SDK."""
import pathlib
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
manifest.write_text(text)
subprocess.run(['flutter', 'pub', 'get'], cwd=app, check=True)
subprocess.run(['dart', 'format', 'lib'], cwd=app, check=True)
subprocess.run(['flutter', 'analyze'], cwd=app, check=True)
print('Sources préparées et analysées. Dans flutter/ : flutter run')
