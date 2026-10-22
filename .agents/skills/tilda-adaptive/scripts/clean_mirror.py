import re,sys
DROP={'rec1234984646','rec1234866606','rec1235016256','rec1848533261','rec1848533271','rec1236513896'}
def clean(slug):
    p=f'_mirror/{slug}/index.html'
    src=open(p,encoding='utf-8').read()
    parts=re.split(r'(?=<div id="rec\d+")',src)
    head=parts[0]; recs=parts[1:]
    last=recs[-1]
    m=re.search(r'((?:</div>\s*)*<script>[^<]*?data-tu-lazy.*)$', last, flags=re.S)
    tail=m.group(1) if m else '</div></body></html>'
    kept=[r for r in recs if re.match(r'<div id="(rec\d+)"',r).group(1) not in DROP]
    dropped=[re.match(r'<div id="(rec\d+)"',r).group(1) for r in recs if re.match(r'<div id="(rec\d+)"',r).group(1) in DROP]
    body=(head+''.join(kept)).rstrip()
    out=body+'\n'+tail
    need=out.count('<div')-out.count('</div>')
    if need>0:
        mm=re.search(r'<script>[^<]*?data-tu-lazy',out) or re.search(r'</body>',out)
        pos=mm.start(); out=out[:pos]+('</div>'*need)+out[pos:]
    if '</body>' not in out: out+='</body>'
    if '</html>' not in out: out+='</html>'
    open(p,'w',encoding='utf-8').write(out)
    # report
    junk=[j for j in ['Tilda Publishing','Бесплатный курс','hello@example.ru','работы моих студентов','Load more'] if j in out]
    print(f'[{slug}] dropped={dropped}')
    print(f'  divbalance {out.count("<div")}/{out.count("</div>")}  body={"</body>" in out} html={"</html>" in out}  forceLoad={"data-tu-lazy" in out}')
    print(f'  junk-left={junk}  sizeKB={len(out)//1024}')
for s in ['02-appstore-shop','03-app-product']:
    clean(s)
