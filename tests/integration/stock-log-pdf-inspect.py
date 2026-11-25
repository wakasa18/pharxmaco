import json,re,pathlib,subprocess,os
from pypdf import PdfReader
import pdfplumber
from PIL import Image,ImageDraw
ROOT=pathlib.Path(__file__).resolve().parents[2];DIR=ROOT/'writable/stock-pdf-memory-fix';OUT=ROOT/'tmp/pdfs/stock-pdf-memory-fix'
POPLER=pathlib.Path('C:/Users/warre/.cache/codex-runtimes/codex-primary-runtime/dependencies/native/poppler/Library/bin/pdftoppm.exe')
expected=json.loads((DIR/'expected-ids.json').read_text(encoding='utf8'));results=[];images=[]
for name in list(expected)+['stress-10000']:
 reader=PdfReader(OUT/(name+'.pdf'));ids=[];texts=[]
 subprocess.run(['node',str(ROOT/'tests/integration/stock-log-pdf-content.cjs'),name,'--quiet'],env={**os.environ,'PDF_REVIEW_DIR':str(OUT)},check=True)
 contents=json.loads((OUT/(name+'-text.json')).read_text(encoding='utf8'))
 assert len(contents)==len(reader.pages),name+' content streams'
 for n,page in enumerate(reader.pages,1):
  items=contents[n-1]['items'];text='\n'.join(i['text'] for i in items);texts.append(text)
  ids.extend(int(i['text'][1:]) for i in items if re.fullmatch(r'#\d+',i['text']) and i['position'] and abs(float(i['position'].split()[0])-38)<.1)
  assert f'Page {n} of {len(reader.pages)}' in text,name+' numbering'
  assert 'PHARXMACO |' in text and 'RPT-' in text,name+' repeated identity'
  assert abs(float(page.mediabox.width)-841.89)<.1 and abs(float(page.mediabox.height)-595.28)<.1,name+' paper'
  if re.search(r'^#\d+\b',text,re.M):assert 'Log / time (PHT)' in text and 'Product / SKU / ID' in text,name+' table heading'
 wanted=list(range(10000,0,-1)) if name=='stress-10000' else expected[name]
 assert ids==wanted,name+' complete ordered record IDs'
 joined='\n'.join(texts)
 if name in ['stock-complete','stock-filtered','stock-search','stock-cashier']:assert 'END-OF-NOTE' in joined,name+' full long note'
 if name=='stock-empty':assert 'No stock logs matched the selected filters.' in joined
 if name=='stock-cashier':assert 'Export QA North' in joined and 'EXPORT-QA-FOREIGN-LOG' not in joined
 if name=='stock-other':assert 'Export QA South' in joined and 'EXPORT-QA-LOG-55' not in joined
 if name=='stress-10000':
  for i in range(137,10001,137):assert f'START-NOTE-{i}' in joined and f'END-NOTE-{i}' in joined,'Complete stress note '+str(i)
  assert 'Pe\u00f1a' in joined and 'Mu\u00f1oz' in joined and '\u20b11.00' in joined,'Unicode font mapping'
  assert '<script>literal</script>' in joined,'Literal text retained without HTML execution'
 samples=sorted(set([1,min(2,len(reader.pages)),max(1,len(reader.pages)//2),len(reader.pages)]))
 for n in samples:
  if n>len(reader.pages):continue
  with pdfplumber.open(OUT/(name+'.pdf')) as pdf:
   page=pdf.pages[n-1]
   assert not [c for c in page.chars if c['x0'] < -1 or c['x1']>page.width+1 or c['top'] < -1 or c['bottom']>page.height+1],name+' page bounds'
  dest=OUT/(name+'-sample-'+str(n))
  subprocess.run([str(POPLER),'-f',str(n),'-l',str(n),'-singlefile','-scale-to','1400','-png',str(OUT/(name+'.pdf')),str(dest)],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
  im=Image.open(str(dest)+'.png').convert('RGB');im.thumbnail((500,355));tile=Image.new('RGB',(520,390),'#ddd');tile.paste(im,((520-im.width)//2,26));ImageDraw.Draw(tile).text((8,6),name+' page '+str(n),fill='black');images.append(tile)
 results.append({'name':name,'pages':len(reader.pages),'record_ids_verified':len(ids),'order_complete':True,'page_numbers_and_headers':True,'rendered_pages':samples})
for start in range(0,len(images),9):
 sheet=Image.new('RGB',(1560,1170),'white')
 for i,im in enumerate(images[start:start+9]):sheet.paste(im,((i%3)*520,(i//3)*390))
 sheet.save(OUT/('contact-'+str(start//9+1)+'.jpg'))
(DIR/'pdf-inspection.json').write_text(json.dumps({'results':results,'rendered_pages':len(images)},indent=2),encoding='utf8')
print(json.dumps({'pdf_files':len(results),'ordered_records_verified':sum(r['record_ids_verified'] for r in results),'rendered_pages':len(images),'results':results},indent=2))
