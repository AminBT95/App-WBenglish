#!/usr/bin/env python3
"""Test the bridge locally without sending secrets to chat or writing them to disk."""
import base64
import getpass
import json
import sys
import urllib.error
import urllib.parse
import urllib.request

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

site = input('URL HTTPS du site de test : ').strip().rstrip('/')
url = urllib.parse.urlsplit(site)
if url.scheme != 'https' or not url.netloc or url.username or url.query or url.fragment:
    sys.exit('Une URL HTTPS sans identifiants est obligatoire.')
username = input('Identifiant de l’élève : ').strip()
password = getpass.getpass('Mot de passe d’application (masqué) : ').replace(' ', '')
if not username or ':' in username or not password:
    sys.exit('Identifiants invalides.')
auth = base64.b64encode(f'{username}:{password}'.encode()).decode()
opener = urllib.request.build_opener(NoRedirect())

def read(route):
    request = urllib.request.Request(site + '/wp-json/wbenglish-mobile/v1' + route,
        headers={'Authorization': 'Basic ' + auth, 'Accept': 'application/json'})
    with opener.open(request, timeout=20) as response:
        return json.load(response)

try:
    courses = read('/courses')
    print('Connexion réussie. Cours accessibles :', len(courses['courses']))
    print('Cours sélectionnés mais indisponibles :', courses.get('unavailable', 0))
    for course in courses['courses']:
        detail = read('/courses/' + str(course['id']))
        lessons = [lesson for section in detail['sections'] for lesson in section['lessons']]
        print(f"Cours {course['id']} : {len(lessons)} activités, "
              f"{sum(x['type'] == 'audio' for x in lessons)} leçons audio.")
except urllib.error.HTTPError as error:
    print('Accès refusé, code HTTP', error.code)
    print('Vérifier le compte de test, les réglages du connecteur et les mots de passe d’application.')
    sys.exit(1)
except (urllib.error.URLError, TimeoutError, ValueError, KeyError):
    sys.exit('Échec de connexion ou réponse inattendue. Vérifier HTTPS, le connecteur et les permaliens.')
