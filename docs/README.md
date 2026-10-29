# Documentation du projet

| Document | Contenu |
|---|---|
| [manuel-utilisation.pdf](manuel-utilisation.pdf) | Présentation de l'application, identifiants de test, parcours visiteur, client, employé et administrateur |
| [charte-graphique.pdf](charte-graphique.pdf) | Palette de couleurs, polices, éléments d'interface, wireframes et maquettes (3 bureau, 3 mobile) |
| [documentation-technique.pdf](documentation-technique.pdf) | Choix techniques, environnement de travail, architecture, modèle de données, diagrammes, sécurité, API, déploiement |
| [gestion-de-projet.pdf](gestion-de-projet.pdf) | Organisation, découpage du travail, étapes, gestion des versions |

## Modifier un document

Les PDF sont générés à partir des fichiers Markdown de `sources/` :

```bash
cd docs/sources
npm install marked@12 playwright
npx playwright install chromium
node generer-pdf.js                         # tous les documents
node generer-pdf.js manuel-utilisation.md   # un seul document
```

- `images/` : captures d'écran, maquettes et diagrammes insérés dans les documents.
- `sources/diagrammes/` : sources des diagrammes. Les fichiers `.mmd` se convertissent avec Mermaid (`npx @mermaid-js/mermaid-cli -i mcd.mmd -o ../../images/mcd.png -c theme.json -s 2 -b white`), et le diagramme de cas d'utilisation se génère avec `python3 cas_utilisation.py`.
