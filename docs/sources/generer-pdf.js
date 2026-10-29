// Génère les PDF du dossier docs/ à partir des fichiers Markdown de docs/sources/.
//
// Utilisation (depuis docs/sources/) :
//   npm install marked@12 playwright
//   npx playwright install chromium
//   node generer-pdf.js
//
// Chaque fichier .md commence par un bloc d'en-tête :
//   ---
//   titre: Documentation technique
//   sous-titre: ...
//   fichier: documentation-technique.pdf
//   ---
const fs = require('fs');
const path = require('path');
const { marked } = require('marked');
const { chromium } = require('playwright');

const ICI = __dirname;
const SORTIE = path.join(ICI, '..');

const CSS = `
@page { size: A4; margin: 20mm 17mm 20mm 17mm; }
* { box-sizing: border-box; }
body { font-family: 'Work Sans', 'Helvetica Neue', Arial, sans-serif; color: #0F2E38; font-size: 10.5pt; line-height: 1.5; margin: 0; }
h1, h2, h3, h4 { font-family: 'Fraunces', Georgia, serif; color: #1B4B5A; line-height: 1.25; }
h1 { font-size: 21pt; margin: 0 0 10pt; padding-bottom: 6pt; border-bottom: 3px solid #E8865A; page-break-before: always; }
h1.premier { page-break-before: avoid; }
h2 { font-size: 14.5pt; margin: 18pt 0 6pt; }
h3 { font-size: 12pt; margin: 14pt 0 4pt; color: #0F2E38; }
p { margin: 0 0 7pt; }
a { color: #1B4B5A; }
ul, ol { margin: 0 0 8pt; padding-left: 18pt; }
li { margin-bottom: 2.5pt; }
code { font-family: 'IBM Plex Mono', Menlo, monospace; font-size: 8.8pt; background: #F2E8D5; padding: 0.5pt 3pt; border-radius: 3px; }
pre { background: #0F2E38; color: #FAF6EE; padding: 8pt 10pt; border-radius: 6px; font-size: 8.6pt; line-height: 1.45; white-space: pre-wrap; page-break-inside: avoid; }
pre code { background: none; color: inherit; padding: 0; }
table { border-collapse: collapse; width: 100%; margin: 6pt 0 10pt; font-size: 9.3pt; page-break-inside: auto; }
tr { page-break-inside: avoid; }
th { background: #1B4B5A; color: #FAF6EE; text-align: left; padding: 4pt 6pt; font-weight: 600; }
td { border-bottom: 1px solid #E3D7BF; padding: 4pt 6pt; vertical-align: top; }
tr:nth-child(even) td { background: #FAF6EE; }
blockquote { margin: 8pt 0; padding: 6pt 10pt; background: #FAF6EE; border-left: 4px solid #E8865A; border-radius: 0 6px 6px 0; }
blockquote p:last-child { margin: 0; }
img { max-width: 100%; display: block; margin: 6pt auto; border-radius: 4px; }
img.capture { border: 1px solid #D9CDB5; }
figure { margin: 8pt 0 12pt; page-break-inside: avoid; text-align: center; }
figcaption { font-size: 8.8pt; color: #5b6b70; margin-top: 3pt; font-style: italic; }
.saut { page-break-after: always; }
.bloc { page-break-inside: avoid; break-inside: avoid; }
.bloc > h1:first-child { page-break-before: always; }
h2, h3 { page-break-after: avoid; break-after: avoid; }
.garde { height: 247mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; }
.garde .bandeau { width: 70pt; height: 6pt; background: #E8865A; border-radius: 3px; margin-bottom: 18pt; }
.garde .marque { font-family: 'Fraunces', Georgia, serif; font-size: 15pt; color: #1B4B5A; margin-bottom: 4pt; }
.garde .marque span { color: #E8865A; }
.garde h1 { border: none; font-size: 32pt; margin: 0 0 8pt; padding: 0; page-break-before: avoid; color: #0F2E38; }
.garde .sous-titre { font-size: 13pt; color: #1B4B5A; margin-bottom: 30pt; }
.garde .infos { font-size: 10pt; color: #3d5359; line-height: 1.7; border-top: 1px solid #D9CDB5; padding-top: 10pt; }
.palette { display: flex; flex-wrap: wrap; gap: 8pt; margin: 8pt 0 12pt; }
.couleur { width: 31%; border: 1px solid #D9CDB5; border-radius: 6px; overflow: hidden; page-break-inside: avoid; }
.couleur .pastille { height: 46pt; }
.couleur .nom { padding: 5pt 7pt 0; font-weight: 600; font-size: 9.5pt; }
.couleur .code { padding: 0 7pt; font-family: 'IBM Plex Mono', monospace; font-size: 8.6pt; }
.couleur .usage { padding: 2pt 7pt 6pt; font-size: 8.3pt; color: #4a5b60; }
.duo { display: flex; gap: 10pt; align-items: flex-start; }
.duo > * { flex: 1; }
.mobiles { display: flex; gap: 10pt; justify-content: center; }
.mobiles figure { flex: 1; margin: 0; }
.mobiles img { max-height: 205mm; width: auto; }
.grand img { max-height: 212mm; width: auto; }
`;

function lireEntete(md) {
  const m = md.match(/^---\n([\s\S]*?)\n---\n/);
  const entete = {};
  if (m) for (const ligne of m[1].split('\n')) { const i = ligne.indexOf(':'); if (i > 0) entete[ligne.slice(0, i).trim()] = ligne.slice(i + 1).trim(); }
  return [entete, m ? md.slice(m[0].length) : md];
}

async function generer(fichier, navigateur) {
  const [entete, corpsMd] = lireEntete(fs.readFileSync(path.join(ICI, fichier), 'utf8'));
  let corps = marked.parse(corpsMd);
  corps = corps.replace('<h1', '<h1 class="premier"');
  // Un titre suivi d'une figure reste sur la même page que la figure
  corps = corps.replace(/((?:<h[123][^>]*>[^<]*<\/h[123]>\s*)+)(<figure[\s\S]*?<\/figure>)/g, '<div class="bloc">$1$2</div>');
  const garde = `
  <section class="garde">
    <div class="bandeau"></div>
    <div class="marque">Vite <span>&amp;</span> Gourmand</div>
    <h1>${entete.titre}</h1>
    <div class="sous-titre">${entete['sous-titre'] || ''}</div>
    <div class="infos">
      ECF – Titre professionnel Développeur Web et Web Mobile (Studi)<br>
      Auteur : Quentin Cire<br>
      Site : https://vite-gourmand-kappa.vercel.app<br>
      Dépôt : https://github.com/Bossim-Rack/V-G<br>
      Version du ${entete.date || ''}
    </div>
  </section>`;
  const html = `<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>${entete.titre}</title>
    <style>${fs.readFileSync(path.join(ICI, 'polices.css'), 'utf8')}${CSS}</style></head>
    <body>${garde}${corps}</body></html>`;
  const tmp = path.join(ICI, '.rendu.html');
  fs.writeFileSync(tmp, html);
  const page = await navigateur.newPage();
  await page.goto('file://' + tmp, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  await page.pdf({
    path: path.join(SORTIE, entete.fichier),
    format: 'A4', printBackground: true, preferCSSPageSize: true,
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: `<div style="width:100%;font-size:7.5pt;color:#6b7b80;padding:0 17mm;display:flex;justify-content:space-between;font-family:Arial,sans-serif;">
      <span>Vite &amp; Gourmand – ${entete.titre}</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>`,
  });
  await page.close();
  fs.unlinkSync(tmp);
  console.log('PDF généré :', entete.fichier);
}

(async () => {
  const fichiers = process.argv.slice(2).length ? process.argv.slice(2) : fs.readdirSync(ICI).filter(f => f.endsWith('.md'));
  const navigateur = await chromium.launch();
  for (const f of fichiers) await generer(f, navigateur);
  await navigateur.close();
})();
