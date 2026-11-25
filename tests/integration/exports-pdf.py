import json,pathlib,re,subprocess
import pdfplumber
from PIL import Image,ImageDraw
ROOT=pathlib.Path(__file__).resolve().parents[2]
DIR=ROOT/'writable/export-audit-20261023'; OUT=ROOT/'tmp/pdfs/export-audit'
POPLER=pathlib.Path('C:/Users/warre/.cache/codex-runtimes/codex-primary-runtime/dependencies/native/poppler/Library/bin/pdftoppm.exe')
state=json.loads((DIR/('state.json' if (DIR/'state.json').exists() else 'fixture-metadata.json')).read_text(encoding='utf8')); results=[]; images=[]
for f in sorted(OUT.glob('*.pdf')):
 if not f.read_bytes().startswith(b'%PDF-'):continue
 printed=f.name.startswith('print-'); pages=[]
 with pdfplumber.open(f) as pdf:
  for p in pdf.pages:
   pages.append({'page':p.page_number,'text':p.extract_text() or '', 'width':p.width,'height':p.height,
    'outside_page':[c['text'] for c in p.chars if c['x0'] < -1 or c['x1']>p.width+1 or c['top'] < -1 or c['bottom']>p.height+1]})
 assert all(not p['outside_page'] for p in pages),f.name+' clipped text outside page'
 if not printed:
  assert all(abs(p['width']-841.89)<.1 and abs(p['height']-595.28)<.1 for p in pages),f.name+' paper'
  assert all(f'Page {p["page"]} of {len(pages)}' in p['text'] for p in pages),f.name+' numbering'
  assert all('PHARXMACO |' in p['text'] and 'RPT-' in p['text'] for p in pages),f.name+' identity'
 text='\n'.join(p['text'] for p in pages)
 if f.stem=='sales-summary':
  assert all(v in text for v in ['\u20b1224.00','\u20b140.00','\u20b124.00','\u20b180.00','Quarantined: 1'])
 if f.stem=='stock-all':
  for log_id in state['logs']:assert len(re.findall(r'^#'+str(log_id)+r'\b',text,re.M))==1,'Missing/duplicate log '+str(log_id)
  assert 'END-OF-NOTE' in text and '-1' in text
 if f.stem=='expiry-all':
  for status in ['EXPIRED','NEAR','GOOD','MISSING']:assert 'Export QA '+status.lower() in text
 if f.stem=='print-refund-slip-receipt': assert 'Quarantined' in text and '80.00' in text
 if f.stem=='print-reprint-receipt':assert 'REPRINT' in text
 (OUT/(f.stem+'-pages.json')).write_text(json.dumps(pages,indent=2,ensure_ascii=False),encoding='utf8')
 results.append({'name':f.stem,'pages':len(pages),'characters':len(text),'outside_page':0,'paper':'browser print' if printed else 'A4 landscape','numbering_verified':not printed})
 subprocess.run([str(POPLER),'-scale-to','1100','-png',str(f),str(OUT/f.stem)],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 for p in sorted(OUT.glob(f.stem+'-[0-9]*.png')):
  im=Image.open(p).convert('RGB');im.thumbnail((390,286));tile=Image.new('RGB',(410,320),'#ddd');tile.paste(im,((410-im.width)//2,25));ImageDraw.Draw(tile).text((8,5),p.stem,fill='black');images.append(tile)
for start in range(0,len(images),16):
 sheet=Image.new('RGB',(1640,1280),'white')
 for i,im in enumerate(images[start:start+16]):sheet.paste(im,((i%4)*410,(i//4)*320))
 sheet.save(OUT/f'latest-contact-{start//16+1}.jpg')
(DIR/'pdf-results.json').write_text(json.dumps({'files':len(results),'pages':sum(r['pages'] for r in results),'results':results},indent=2),encoding='utf8')
print(f'Verified and rendered {len(results)} PDFs / {sum(r["pages"] for r in results)} pages, including five browser print outputs.')
