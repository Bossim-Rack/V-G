"""Génère docs/images/cas-utilisation.svg (diagramme de cas d'utilisation UML)."""
import html, os

SECTIONS = [
    ("Visiteur", [
        "Consulter l'accueil et les avis validés", "Consulter et filtrer les menus",
        "Voir le détail d'un menu et ses conditions", "Créer un compte", "Contacter l'entreprise"]),
    ("Client", [
        "Se connecter / réinitialiser son mot de passe", "Commander un menu",
        "Modifier ou annuler sa commande (tant qu'elle est en attente)", "Suivre sa commande",
        "Donner un avis (note de 1 à 5 + commentaire)", "Gérer son compte (infos, mot de passe, suppression)"]),
    ("Employé", [
        "Gérer les menus, plats et horaires", "Filtrer les commandes (statut, client)",
        "Faire avancer le statut des commandes", "Modifier / annuler une commande après contact du client",
        "Valider ou refuser les avis"]),
    ("Administrateur", [
        "Créer un compte employé", "Désactiver un compte employé",
        "Consulter les commandes par menu (graphique)", "Calculer le chiffre d'affaires par menu et par période"]),
]
W = 960; ACT_X = 150; SYS_X = 290; SYS_W = W - SYS_X - 20
ELL_W = SYS_W - 140; ELL_H = 40; ROW_H = 50
PETROL, CORAIL, ENCRE, SABLE = "#1B4B5A", "#E8865A", "#0F2E38", "#F2E8D5"
out = []; y = 70; actor_pos = {}
for nom, cas in SECTIONS:
    rows = len(cas); h = rows * ROW_H + 50
    out.append(f'<rect x="{SYS_X+15}" y="{y}" width="{SYS_W-30}" height="{h-12}" rx="10" fill="{SABLE}" fill-opacity="0.45" stroke="#c9b99a" stroke-dasharray="4 4"/>')
    out.append(f'<text x="{SYS_X+30}" y="{y+22}" font-size="13" font-weight="600" fill="{PETROL}">Cas propres au profil {html.escape(nom)}</text>')
    ay = y + h / 2 - 6; actor_pos[nom] = ay
    for i, c in enumerate(cas):
        cx = SYS_X + SYS_W / 2; cy = y + 40 + i * ROW_H + ELL_H / 2
        out.append(f'<line x1="{ACT_X+22}" y1="{ay}" x2="{cx-ELL_W/2}" y2="{cy}" stroke="#8aa0a8" stroke-width="1"/>')
        out.append(f'<ellipse cx="{cx}" cy="{cy}" rx="{ELL_W/2}" ry="{ELL_H/2}" fill="white" stroke="{PETROL}" stroke-width="1.5"/>')
        words = c.split(' '); lines = []; cur = ''
        for wd in words:
            if len(cur) + len(wd) + 1 > 70: lines.append(cur); cur = wd
            else: cur = (cur + ' ' + wd).strip()
        lines.append(cur)
        for k, ln in enumerate(lines):
            out.append(f'<text x="{cx}" y="{cy + (k - (len(lines)-1)/2) * 15 + 5}" font-size="13" text-anchor="middle" fill="{ENCRE}">{html.escape(ln)}</text>')
    y += h
H = y + 60
def stick(x, y, label):
    return (f'<g stroke="{ENCRE}" stroke-width="2" fill="none"><circle cx="{x}" cy="{y-30}" r="9" fill="white"/>'
            f'<line x1="{x}" y1="{y-21}" x2="{x}" y2="{y+4}"/><line x1="{x-15}" y1="{y-12}" x2="{x+15}" y2="{y-12}"/>'
            f'<line x1="{x}" y1="{y+4}" x2="{x-12}" y2="{y+22}"/><line x1="{x}" y1="{y+4}" x2="{x+12}" y2="{y+22}"/></g>'
            f'<text x="{x}" y="{y+40}" font-size="14" font-weight="600" text-anchor="middle" fill="{ENCRE}">{html.escape(label)}</text>')
actors = ''.join(stick(ACT_X, actor_pos[n], n) for n, _ in SECTIONS)
def gen(a, b):  # flèche de généralisation (triangle creux) de a vers b, sur la gauche
    ya, yb = actor_pos[a] - 30, actor_pos[b] + 40
    x = ACT_X - 50 - 22 * [s[0] for s in SECTIONS].index(a)
    return (f'<path d="M{ACT_X-18},{ya} H{x} V{yb+16}" fill="none" stroke="{CORAIL}" stroke-width="1.5"/>'
            f'<path d="M{x-7},{yb+16} L{x+7},{yb+16} L{x},{yb+3} Z" fill="white" stroke="{CORAIL}" stroke-width="1.5"/>')
gens = gen("Client", "Visiteur") + gen("Employé", "Client") + gen("Administrateur", "Employé")
svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}" font-family="Work Sans, Arial, sans-serif">
<rect width="{W}" height="{H}" fill="white"/>
<rect x="{SYS_X}" y="20" width="{SYS_W}" height="{H-70}" rx="14" fill="none" stroke="{PETROL}" stroke-width="2"/>
<text x="{SYS_X + SYS_W/2}" y="48" font-size="17" font-weight="700" text-anchor="middle" fill="{PETROL}">Application Vite &amp; Gourmand</text>
{''.join(out)}{actors}{gens}
<text x="20" y="{H-12}" font-size="11.5" fill="#555">Flèche orange à triangle creux : héritage (chaque acteur peut aussi faire tout ce que fait l'acteur pointé).</text>
</svg>'''
dest = os.path.join(os.path.dirname(__file__), '..', '..', 'images', 'cas-utilisation.svg')
open(dest, 'w', encoding='utf-8').write(svg); print('écrit', os.path.normpath(dest))
