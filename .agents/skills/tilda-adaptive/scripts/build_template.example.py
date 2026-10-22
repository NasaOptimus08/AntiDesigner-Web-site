import re,sys,shutil,os
SRC='_mirror/01-baikal-fresh/index.html'
OUTDIR='_mirror/01-baikal'
s=open(SRC,encoding='utf-8').read()

DROP={'rec1234984646','rec1234866606','rec1235016256','rec1848533261','rec1848533271','rec1236513896'}

MASTER_CSS='''<style id="bkx-master">
.t-zoomer__wrapper,.t-zoomer,.t-zoomer__ui-wrapper{display:none!important}
.bkx,.bkxb,.bkxh{font-family:'Manrope',Arial,sans-serif;color:#232c45;box-sizing:border-box}
.bkx *,.bkxb *,.bkxh *{box-sizing:border-box;margin:0}
.bkx-wrap,.bkxb-wrap{max-width:1200px;margin:0 auto;padding:0 24px}
.bkx-chip,.bkxb-chip{display:inline-block;background:#e3e4ff;color:#5860ed;font-size:14px;font-weight:600;padding:8px 16px;border-radius:500px}
.bkxb-h{font-size:40px;font-weight:700;line-height:1.08;letter-spacing:-.02em}
.bkxb-lead{font-size:16px;line-height:1.5;color:#535353}
.bkxb-btn{display:inline-flex;align-items:center;justify-content:center;background:#5860ed;color:#fff;font-size:16px;font-weight:600;padding:16px 30px;border-radius:500px;border:none;cursor:pointer;text-decoration:none;transition:filter .15s}
.bkxb-btn:hover{filter:brightness(1.08)}
.bkxb-btn--white{background:#fff;color:#5860ed}
/* header */
.bkxh{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.86);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid #eef0f3}
.bkxh__in{max-width:1400px;margin:0 auto;padding:14px 40px;display:flex;align-items:center;gap:26px;position:relative}
.bkxh__logo{display:flex;align-items:center;gap:9px;font-weight:800;font-size:21px;color:#232c45;letter-spacing:-.02em;text-decoration:none}
.bkxh__nav{display:flex;gap:26px;margin:0 auto}
.bkxh__nav a{font-size:16px;color:#535353;text-decoration:none;transition:color .15s}
.bkxh__nav a:hover{color:#5860ed}
.bkxh__cta{background:#e3e4ff;color:#5860ed;border:1px solid #d5d7fb;border-radius:500px;padding:13px 24px;font-size:15px;font-weight:600;text-decoration:none}
.bkxh__cta:hover{background:#d5d7fb}
.bkxh__burger{display:none;background:none;border:none;cursor:pointer;padding:8px}
.bkxh__burger span{display:block;width:24px;height:2px;background:#232c45;margin:5px 0;border-radius:2px}
/* marquee */
.bkx-mq{overflow:hidden}
.bkx-mq__track{display:flex;gap:20px;width:max-content;animation:bkxScroll 48s linear infinite}
@keyframes bkxScroll{from{transform:translateX(0)}to{transform:translateX(calc(-50% - 10px))}}
.bkx-city{position:relative;width:275px;height:355px;border-radius:20px;overflow:hidden;flex:0 0 auto;background-size:cover;background-position:center}
.bkx-city--sq{width:316px;height:316px}
.bkx-city__lbl{position:absolute;left:20px;top:20px;height:40px;padding:0 18px;border-radius:500px;background:rgba(255,255,255,.18);backdrop-filter:blur(9px);-webkit-backdrop-filter:blur(9px);color:#fff;font-size:14px;font-weight:500;display:inline-flex;align-items:center;white-space:nowrap}
/* statement */
.bkx-statement{padding:54px 0 34px;text-align:center}
.bkx-statement p{font-size:44px;font-weight:700;line-height:1.12;letter-spacing:-.02em;max-width:1150px;margin:0 auto}
.bkx-ava{width:78px;height:46px;border-radius:60px;object-fit:cover;display:inline-block;vertical-align:middle;margin:0 12px}
.bkx-accent{color:#5860ed}
/* talcy */
.bkxb-talcy{padding:44px 0}
.bkxb-talcy__grid{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
.bkxb-talcy h3{margin:14px 0 14px}
.bkxb-talcy__thumbs{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:24px}
.bkxb-talcy__thumbs div{aspect-ratio:1;border-radius:14px;background-size:cover;background-position:center}
.bkxb-talcy__big{border-radius:24px;background-size:cover;background-position:center;min-height:470px}
/* shaman */
.bkx-shaman{padding:24px 0 44px}
.bkx-shaman__head{max-width:520px;margin:0 0 26px auto;text-align:left}
.bkx-shaman__head h3{font-size:40px;font-weight:700;line-height:1.08;letter-spacing:-.02em;margin:14px 0 12px}
.bkx-shaman__head p{font-size:16px;color:#535353;line-height:1.5}
.bkx-shaman__grid{display:grid;grid-template-columns:1.5fr 1fr 1fr;grid-template-rows:auto auto;gap:20px}
.bkx-ph{position:relative;border-radius:20px;overflow:hidden;background-size:cover;background-position:center;min-height:246px}
.bkx-ph--big{grid-column:1;grid-row:1/3;min-height:512px}
.bkx-shaman__grid>.bkx-purple{grid-column:2;grid-row:1}
.bkx-shaman__grid>.bkx-ph:not(.bkx-ph--big){grid-column:3;grid-row:1}
.bkx-ph__lbl{position:absolute;left:18px;bottom:18px;padding:9px 16px;border-radius:500px;background:rgba(255,255,255,.2);backdrop-filter:blur(9px);-webkit-backdrop-filter:blur(9px);color:#fff;font-size:14px;font-weight:500}
.bkx-purple{background:#e3e4ff;border-radius:20px;padding:26px;display:flex;flex-direction:column;justify-content:center;min-height:246px}
.bkx-purple h4{font-size:22px;font-weight:700;line-height:1.2;margin-bottom:12px}
.bkx-purple p{font-size:15px;color:#535353;line-height:1.5}
/* zhd intro */
.bkxb-zhd{padding:24px 0 20px}
.bkxb-zhd__grid{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}
.bkxb-zhd h3{margin-top:14px}
.bkxb-zhd__aside{display:flex;flex-direction:column;gap:22px;align-items:flex-start}
/* zhd cards */
.bkx-day{position:relative;width:338px;height:256px;border-radius:20px;overflow:hidden;flex:0 0 auto;background-size:cover;background-position:center;color:#fff;padding:20px;display:flex;flex-direction:column;justify-content:space-between}
.bkx-day::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(20,25,45,.05),rgba(20,25,45,.72))}
.bkx-day>*{position:relative;z-index:1}
.bkx-day__lbl{align-self:flex-start;background:rgba(255,255,255,.18);backdrop-filter:blur(9px);-webkit-backdrop-filter:blur(9px);border-radius:500px;padding:8px 16px;font-size:14px;font-weight:500}
.bkx-tags{display:flex;flex-wrap:wrap;gap:8px}
.bkx-tag{background:rgba(255,255,255,.22);backdrop-filter:blur(6px);border-radius:500px;padding:7px 14px;font-size:13px}
/* organizers */
.bkxb-org{padding:44px 0}
.bkxb-org h3{margin-bottom:30px;max-width:520px}
.bkxb-org__grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.bkxb-orgcard{display:grid;grid-template-columns:200px 1fr;gap:26px;background:#f8f8f8;border-radius:20px;padding:26px;align-items:center}
.bkxb-orgcard__ph{width:100%;height:240px;border-radius:16px;background-size:cover;background-position:center top}
.bkxb-orgcard__head{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
.bkxb-orgcard__nm{font-size:22px;font-weight:700}
.bkxb-orgcard__age{font-size:16px;font-weight:700;color:#5860ed}
.bkxb-orgcard__role{font-size:14px;color:#9d9d9d;margin:4px 0 16px}
.bkxb-orgcard__q{font-size:16px;line-height:1.4;font-weight:600;color:#232c45}
/* past */
.bkx-past{padding:56px 0}
.bkx-past h3{text-align:center;font-size:40px;font-weight:700;max-width:820px;margin:0 auto 30px;letter-spacing:-.02em;line-height:1.1}
.bkx-past .bkx-mq{overflow:hidden;padding:42px 0}
/* reviews */
.bkx-reviews{padding:56px 0}
.bkx-reviews__grid{display:grid;grid-template-columns:1fr 1fr;align-items:center;gap:0}
.bkx-reviews__head{padding-left:max(24px,calc((100vw - 1200px)/2 + 24px));padding-right:24px}
.bkx-reviews__head h3{font-size:40px;font-weight:700;letter-spacing:-.02em;margin-top:14px;line-height:1.1;max-width:400px}
.bkx-reviews__gal{overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent 0,#000 22%);mask-image:linear-gradient(90deg,transparent 0,#000 22%)}
.bkx-rev{flex:0 0 391px;height:496px;background:#f8f8f8;border-radius:20px;padding:36px 34px;display:flex;flex-direction:column}
.bkx-rev__top{display:flex;align-items:center;gap:13px;margin-bottom:14px}
.bkx-rev__ava{width:56px;height:56px;border-radius:50%;object-fit:cover;flex:0 0 auto}
.bkx-rev__nm{font-size:20px;font-weight:700}
.bkx-rev__dt{font-size:14px;color:#9d9d9d}
.bkx-stars{color:#FFB020;font-size:16px;letter-spacing:2px;margin:20px 0 16px}
.bkx-rev__q{font-size:18px;line-height:1.5;color:#232c45;font-weight:500}
.bkx-rev__quote{margin-top:auto;font-size:70px;line-height:.4;color:#d5d7fb;font-family:Georgia,serif;height:34px}
/* steps */
.bkx-steps{padding:70px 0}
.bkx-steps__head{display:flex;justify-content:space-between;align-items:flex-start;gap:30px;margin-bottom:36px;flex-wrap:wrap}
.bkx-steps__aside{display:flex;align-items:center;gap:26px;flex-wrap:wrap;padding-top:10px}
.bkx-steps__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.bkx-step{background:#f8f8f8;border-radius:24px;padding:32px 30px;min-height:270px;display:flex;flex-direction:column}
.bkx-step__n{font-size:15px;color:#9d9d9d;margin-bottom:auto}
.bkx-step h4{font-size:22px;font-weight:700;line-height:1.18;letter-spacing:-.01em;margin:26px 0 10px;color:#232c45}
.bkx-step p{font-size:15px;line-height:1.5;color:#535353}
/* tariffs */
.bkxb-tar{padding:44px 0}
.bkxb-tar h3{margin:14px 0 30px}
.bkxb-tar__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.bkxb-tarcard{background:#f8f8f8;border-radius:22px;padding:32px 30px;display:flex;flex-direction:column}
.bkxb-tarcard--hot{background:#5860ed;color:#fff}
.bkxb-tarcard__badge{display:inline-block;align-self:flex-start;background:rgba(255,255,255,.22);border-radius:500px;padding:8px 16px;font-size:14px;margin-bottom:16px}
.bkxb-tarcard__price{font-size:38px;font-weight:700;letter-spacing:-.02em}
.bkxb-tarcard__per{font-size:14px;color:#9d9d9d;margin-bottom:22px}
.bkxb-tarcard--hot .bkxb-tarcard__per{color:#d5d7fb}
.bkxb-tarcard__li{display:flex;align-items:flex-start;gap:10px;font-size:15px;margin:11px 0;line-height:1.4}
.bkxb-tarcard__li b{color:#5860ed;font-weight:700}
.bkxb-tarcard--hot .bkxb-tarcard__li b{color:#fff}
.bkxb-tarcard .bkxb-btn{width:100%;margin-top:22px}
/* faq */
.bkx-faq{padding:70px 0}
.bkx-faq__wrap{max-width:820px;margin:0 auto;padding:0 24px;text-align:center}
.bkx-faq .bkxb-h,.bkx-faq h2{font-size:44px;font-weight:700;letter-spacing:-.02em;margin:14px 0 34px}
.bkx-acc-list{text-align:left;margin:0 auto}
.bkx-acc{border-bottom:1px solid #ededf3}
.bkx-acc__q{width:100%;text-align:left;background:none;border:none;cursor:pointer;padding:26px 46px 26px 0;position:relative;font-size:22px;font-weight:700;color:#232c45;line-height:1.25;font-family:inherit}
.bkx-acc__q::after{content:"+";position:absolute;right:4px;top:50%;transform:translateY(-50%);font-size:30px;font-weight:400;color:#5860ed;transition:transform .25s}
.bkx-acc.open .bkx-acc__q::after{content:"\\2013"}
.bkx-acc__a{max-height:0;overflow:hidden;transition:max-height .3s ease;color:#535353;font-size:16px;line-height:1.55}
.bkx-acc__a div{padding:0 40px 26px 0}
/* final */
.bkxb-final{padding:20px 0 60px}
.bkxb-final__box{position:relative;background:#232c45;border-radius:28px;overflow:hidden;padding:64px 56px;display:grid;grid-template-columns:1.1fr 1fr;gap:30px;align-items:center;color:#fff}
.bkxb-final__box h2{font-size:40px;font-weight:700;line-height:1.06;letter-spacing:-.02em}
.bkxb-final__box p{color:#cfd2dc;font-size:16px;line-height:1.5;margin:16px 0 26px;max-width:380px}
.bkxb-final__pics{position:relative;height:340px}
.bkxb-final__pics div{position:absolute;border-radius:22px;background-size:cover;background-position:center;box-shadow:0 18px 40px rgba(0,0,0,.25)}
.bkxb-fp1{width:190px;height:190px;right:2%;top:6%;transform:rotate(6deg)}
.bkxb-fp2{width:200px;height:200px;left:4%;top:34%;transform:rotate(-8deg)}
.bkxb-fp3{width:175px;height:175px;right:16%;bottom:2%;transform:rotate(10deg)}
.bkxb-final__contacts{max-width:1200px;margin:26px auto 0;padding:0 24px;display:flex;gap:30px;flex-wrap:wrap;color:#8a8e97;font-size:15px}
.bkxb-final__contacts a{color:#232c45;font-weight:600;text-decoration:none}
/* modal */
.bkx-modal{position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(20,25,45,.55);backdrop-filter:blur(4px)}
.bkx-modal.open{display:flex}
.bkx-modal__box{background:#fff;border-radius:22px;max-width:440px;width:100%;padding:38px 32px;position:relative}
.bkx-modal__x{position:absolute;right:16px;top:14px;width:36px;height:36px;background:none;border:none;font-size:30px;color:#b3b6bd;cursor:pointer}
.bkx-modal h3{font-size:26px;font-weight:700;margin-bottom:8px}
.bkx-modal p{color:#535353;font-size:15px;margin-bottom:22px}
.bkx-modal input{width:100%;border:1px solid #e3e5ea;border-radius:12px;padding:15px 16px;font-size:16px;font-family:inherit;margin-bottom:12px;outline:none}
.bkx-modal input:focus{border-color:#5860ed}
.bkx-modal .bkxb-btn{width:100%}
/* responsive */
@media(max-width:900px){
.bkxh__in{padding:12px 18px}.bkxh__nav{display:none}.bkxh__nav.open{display:flex;flex-direction:column;position:absolute;left:0;right:0;top:100%;margin:0;background:rgba(255,255,255,.98);backdrop-filter:blur(12px);padding:18px 20px 22px;gap:18px;border-bottom:1px solid #eef0f3;box-shadow:0 16px 34px rgba(35,44,69,.1)}.bkxh__nav a{font-size:18px}.bkxh__cta{display:none}.bkxh__burger{display:block;margin-left:auto}
.bkxb-talcy__grid,.bkxb-zhd__grid,.bkxb-org__grid,.bkxb-tar__grid,.bkxb-final__box{grid-template-columns:1fr}
.bkxb-h{font-size:30px}.bkxb-talcy__big{min-height:280px}.bkxb-orgcard{grid-template-columns:1fr}.bkxb-orgcard__ph{height:280px}
.bkxb-final__box{padding:40px 26px}.bkxb-final__pics{height:300px}.bkxb-final__box h2{font-size:30px}
.bkx-shaman__grid{grid-template-columns:1fr;grid-template-rows:none}.bkx-shaman__grid>*{grid-column:1!important;grid-row:auto!important}.bkx-ph--big{min-height:280px}.bkx-shaman__head h3{font-size:30px}
.bkx-reviews__grid{grid-template-columns:1fr;gap:20px}.bkx-reviews__head{padding-left:18px;padding-right:18px}.bkx-reviews__head h3{font-size:26px;max-width:none}.bkx-reviews__gal{-webkit-mask-image:none;mask-image:none}.bkx-rev{flex-basis:82vw;height:auto;min-height:420px}
.bkx-past h3{font-size:26px}.bkx-statement p{font-size:26px}
.bkx-steps__grid{grid-template-columns:1fr}
.bkx-faq .bkxb-h,.bkx-faq h2{font-size:28px}
}
@media(max-width:640px){
.bkx-city{width:74vw;height:300px}.bkx-city--sq{width:74vw;height:74vw;max-height:320px}.bkx-day{width:80vw;height:210px}
.bkxb-final__pics{height:240px}.bkxb-fp1,.bkxb-fp3{width:140px;height:140px}.bkxb-fp2{width:150px;height:150px}
}
</style>'''

# ---------- blocks ----------
HEADER='''<header class="bkxh"><div class="bkxh__in">
<a class="bkxh__logo" href="#top"><svg width="28" height="28" viewBox="0 0 28 28" fill="none"><path d="M2 22L10 9l4 6 3-4 9 11H2z" fill="#5860ED"/><circle cx="21" cy="6.5" r="3.2" fill="#232C45"/></svg>давай в путь</a>
<nav class="bkxh__nav"><a href="#about">О нас</a><a href="#adv">Преимущества</a><a href="#plan">План поездки</a><a href="#reviews">Отзывы</a><a href="#contacts">Контакты</a></nav>
<a class="bkxh__cta" data-lead href="#contacts">Связаться с нами</a>
<button class="bkxh__burger" aria-label="Меню" onclick="var n=this.parentElement.querySelector('.bkxh__nav');n.classList.toggle('open')"><span></span><span></span><span></span></button>
</div></header><span id="top"></span>'''

def city(l,i): return f'<div class="bkx-city" style="background-image:url({i})"><span class="bkx-city__lbl">{l}</span></div>'
HC=[('Ольхон','img/75b2e9c0820b1a25.webp'),('Ушканьи острова','img/8064b4610a1b84be.webp'),('Листвянка','img/30dc08f3fdcdc381.webp'),('Шаман-скала','img/21b6ab4f7b07a2ee.webp'),('Слюдянка','img/8644edea9bce691f.webp'),('Бухта Песчаная','img/24566a96636c2587.webp')]
HERO=f'<section class="bkx" id="about" style="padding:26px 0 10px"><div class="bkx-mq"><div class="bkx-mq__track">{"".join(city(*c) for c in HC)*2}</div></div></section>'

STATEMENT='<section class="bkx bkx-statement"><div class="bkx-wrap"><p>Байкал&nbsp;— это не просто путешествие,<img class="bkx-ava" src="img/9d30a443a708734e.webp" alt=""> это перезагрузка на природе без фильтров, с <span class="bkx-accent">крутым настроем и командой</span>, как одна семья</p></div></section>'

TALCY='<section class="bkxb bkxb-talcy" id="plan"><div class="bkxb-wrap"><div class="bkxb-talcy__grid"><div><span class="bkxb-chip">Тальцы</span><h3 class="bkxb-h">Архитектурно-этнографический музей под открытым небом</h3><p class="bkxb-lead">Здесь можно погрузиться в быт сибиряков, увидеть, как жили русские переселенцы, и поучаствовать в народных ремёслах.</p><div class="bkxb-talcy__thumbs"><div style="background-image:url(assets/6c16744cf91990bd.jpg)"></div><div style="background-image:url(assets/e741126f0fd4b016.jpg)"></div><div style="background-image:url(assets/d2bdb365233c2d61.jpg)"></div></div></div><div class="bkxb-talcy__big" style="background-image:url(assets/4da3e63673355dd4.jpg)"></div></div></div></section>'

SHAMAN='<section class="bkx bkx-shaman"><div class="bkx-wrap"><div class="bkx-shaman__head"><span class="bkx-chip">Шаман-скала</span><h3>Священное место на Ольхоне</h3><p>Священное место на Ольхоне, одна из визитных карточек Байкала. Каждому человеку выдаётся одноместная палатка и спальный мешок.</p></div><div class="bkx-shaman__grid"><div class="bkx-ph bkx-ph--big" style="background-image:url(assets/60de69e7bab382e7.jpg)"><span class="bkx-ph__lbl">Посещение храма Ольхона</span></div><div class="bkx-purple"><h4>Проводим ночь на&nbsp;берегу Байкала</h4><p>Каждому человеку выдаётся одноместная палатка и спальный мешок.</p></div><div class="bkx-ph" style="background-image:url(assets/3d70da5a3b4fd98e.jpg)"><span class="bkx-ph__lbl">Озеро Байкал</span></div></div></div></section>'

ZHD_INTRO='<section class="bkxb bkxb-zhd"><div class="bkxb-wrap"><div class="bkxb-zhd__grid"><div><span class="bkxb-chip">План на следующие дни</span><h3 class="bkxb-h">Кругобайкальская железная дорога</h3></div><div class="bkxb-zhd__aside"><p class="bkxb-lead">По этой железной дороге мы проведём остаток нашего путешествия, посетив 3 города.</p><a class="bkxb-btn" data-lead href="#contacts">Забронировать</a></div></div></div></section>'

def day(t,i,tg): return f'<div class="bkx-day" style="background-image:url({i})"><span class="bkx-day__lbl">{t}</span><div class="bkx-tags">{"".join(f"<span class=bkx-tag>{x}</span>" for x in tg)}</div></div>'
DAYS=[('Листвянка','assets/13c6c03b07dcd2b3.jpg',['Чаепитие','Баня','Купание в заливе']),('Чивыркуйский залив','assets/d92acd4d227eb395.jpg',['Рыбный обед','Купание в заливе']),('Баргузинская долина','assets/15f1824ad4b71f2a.jpg',['Сбор трав','Чаепитие'])]
ZHD_CARDS=f'<section class="bkx" style="padding:6px 0 40px"><div class="bkx-mq"><div class="bkx-mq__track">{"".join(day(*d) for d in DAYS)*4}</div></div></section>'

ORG='<section class="bkxb bkxb-org" id="team"><div class="bkxb-wrap"><h3 class="bkxb-h">Организаторы вашего путешествия</h3><div class="bkxb-org__grid"><div class="bkxb-orgcard"><div class="bkxb-orgcard__ph" style="background-image:url(assets/8232bf15b84e41bd.jpg)"></div><div><div class="bkxb-orgcard__head"><span class="bkxb-orgcard__nm">Дмитрий Чикунов</span><span class="bkxb-orgcard__age">26 лет</span></div><div class="bkxb-orgcard__role">отвечаю за настроение · посетил 90 стран</div><div class="bkxb-orgcard__q">Я знаю, что тебе нужно для хорошего отдыха: оставить дела дома, уехать с близкими на Байкал и на недельку забыть о бытовых вещах, наслаждаясь природой.</div></div></div><div class="bkxb-orgcard"><div class="bkxb-orgcard__ph" style="background-image:url(assets/9113e31aeecbf2ba.jpg)"></div><div><div class="bkxb-orgcard__head"><span class="bkxb-orgcard__nm">Вероника Шарова</span><span class="bkxb-orgcard__age">25 лет</span></div><div class="bkxb-orgcard__role">отвечаю за уют и заботу · посетила 60 стран</div><div class="bkxb-orgcard__q">Позабочусь, чтобы дорога прошла легко: продуманный маршрут, уютный автобус и ни одной скучной минуты в пути до самого Байкала.</div></div></div></div></div></section>'

PASTIMG=['assets/18b445a4497306e3.jpg','assets/1ba5cba884b8d515.jpg','assets/38d3eaceacf0eded.jpg','assets/a041ca4e40612b44.jpg','assets/b0e43243adc53569.jpg','assets/ba1509ca942c0a62.jpg','assets/e5ab94e10e6b9b7c.jpg','assets/efd8041bec23399e.jpg']
ROT=[-4,3,-2,4,-3,2,-4,3]
past_cards=''.join(f'<div class="bkx-city bkx-city--sq" style="background-image:url({u});transform:rotate({ROT[i%8]}deg)"></div>' for i,u in enumerate(PASTIMG*2))
PAST=f'<section class="bkx bkx-past"><div class="bkx-wrap"><h3>Давай посмотрим на то, как у нас это всё проходило раньше</h3></div><div class="bkx-mq"><div class="bkx-mq__track">{past_cards}</div></div></section>'

STEPS_ITEMS=[('01','Вы оставляете заявку','Оставляете заявку на сайте, и мы связываемся с вами, чтобы уточнить удобные даты.'),('02','Подбираем группу','Определяем, в какую группу вас записать, или собираем группу под вас индивидуально.'),('03','Бронируем места','Фиксируем даты, закрепляем за вами места в автобусе и на всех локациях маршрута.'),('04','Готовимся к поездке','Присылаем список вещей и подробную программу, отвечаем на все ваши вопросы.'),('05','Отправляемся на Байкал','Встречаемся на месте сбора и стартуем наше путешествие всей компанией.'),('06','Отдыхаете в удовольствие','А после делитесь отзывом, чтобы и другие захотели отдохнуть вместе с нами!')]
steps_cards=''.join(f'<div class="bkx-step"><div class="bkx-step__n">{n}</div><h4>{t}</h4><p>{d}</p></div>' for n,t,d in STEPS_ITEMS)
STEPS=f'<section class="bkx bkx-steps" id="adv2"><div class="bkx-wrap"><div class="bkx-steps__head"><div><span class="bkx-chip">Организация</span><h3 class="bkxb-h" style="margin-top:14px">Как всё будет происходить</h3></div><div class="bkx-steps__aside"><p class="bkxb-lead" style="max-width:300px">Что требуется от вас, это приехать вовремя на место сбора, всё :)</p><a class="bkxb-btn" data-lead href="#contacts">Тогда я в деле!</a></div></div><div class="bkx-steps__grid">{steps_cards}</div></div></section>'

REVIEWS_DATA=[('Артём','img/people/a1.jpg','май 2026, Байкал','Редко выбираюсь в отпуск, но эмоции от Байкала не передать словами. Вернулся будто другим человеком, полным сил и вдохновения!'),('Ольга','img/people/a2.jpg','июнь 2026, Байкал','Впервые почувствовала настоящее единение с природой. Вы попали в самое сердечко, спасибо за эту незабываемую неделю!'),('Егор','img/people/a3.jpg','июнь 2026, Байкал','Организация без единого сбоя: ни о чём не пришлось думать, только наслаждаться видами и тёплой компанией. Топ во всём!'),('Марина','img/people/a4.jpg','июль 2026, Байкал','Поехала одна, а вернулась с друзьями на всю жизнь. Атмосфера настолько тёплая, будто знакомы уже сто лет.'),('Дмитрий','img/people/a5.jpg','июль 2026, Байкал','Фотограф снял такие кадры, что теперь весь телефон в Байкале. Однозначно лучшая неделя за последние несколько лет.'),('Наталья','img/people/a6.jpg','август 2026, Байкал','Домашняя еда, баня, чай у костра, забота чувствовалась на каждом шагу. Обязательно вернёмся сюда уже всей семьёй!'),('Павел','img/people/a1.jpg','август 2026, Байкал','Маршрут продуман до мелочей, автобус удобный, а гиды живые и весёлые. Рекомендую однозначно всем без исключения.'),('Ксения','img/people/a2.jpg','сентябрь 2026, Байкал','Байкал осенью это космос: тишина, багряные склоны и невероятные закаты над водой. Спасибо за эту сказку наяву!')]
rev_cards=''.join(f'<div class="bkx-rev"><div class="bkx-rev__top"><img class="bkx-rev__ava" src="{a}" alt=""><div><div class="bkx-rev__nm">{n}</div><div class="bkx-rev__dt">{d}</div></div></div><div class="bkx-stars">★★★★★</div><div class="bkx-rev__q">{q}</div><div class="bkx-rev__quote">”</div></div>' for n,a,d,q in REVIEWS_DATA)*2
REVIEWS=f'<section class="bkx bkx-reviews" id="reviews"><div class="bkx-reviews__grid"><div class="bkx-reviews__head"><span class="bkx-chip">Отзывы</span><h3>Что говорят о нас наши клиенты</h3></div><div class="bkx-reviews__gal"><div class="bkx-mq"><div class="bkx-mq__track">{rev_cards}</div></div></div></div></section>'

TAR='<section class="bkxb bkxb-tar"><div class="bkxb-wrap"><span class="bkxb-chip">Тарифы</span><h3 class="bkxb-h">Выберите подходящий тариф для путешествия</h3><div class="bkxb-tar__grid"><div class="bkxb-tarcard"><div class="bkxb-tarcard__price">45 000 ₽</div><div class="bkxb-tarcard__per">за человека / 7 дней</div><div class="bkxb-tarcard__li"><b>✓</b>Место в группе до 30 человек</div><div class="bkxb-tarcard__li"><b>✓</b>Проживание в кемпинге</div><div class="bkxb-tarcard__li"><b>✓</b>Трёхразовое питание</div><a class="bkxb-btn" data-lead href="#contacts">Забронировать</a></div><div class="bkxb-tarcard bkxb-tarcard--hot"><span class="bkxb-tarcard__badge">Самый популярный</span><div class="bkxb-tarcard__price">65 000 ₽</div><div class="bkxb-tarcard__per">за человека / 7 дней</div><div class="bkxb-tarcard__li"><b>✓</b>Всё из тарифа «Эконом»</div><div class="bkxb-tarcard__li"><b>✓</b>Проживание в гостевых домах</div><div class="bkxb-tarcard__li"><b>✓</b>Работа личного фотографа</div><a class="bkxb-btn bkxb-btn--white" data-lead href="#contacts">Забронировать</a></div><div class="bkxb-tarcard"><div class="bkxb-tarcard__price">120 000 ₽</div><div class="bkxb-tarcard__per">за человека / 7 дней</div><div class="bkxb-tarcard__li"><b>✓</b>Всё из тарифа «Стандарт»</div><div class="bkxb-tarcard__li"><b>✓</b>Индивидуальный маршрут</div><div class="bkxb-tarcard__li"><b>✓</b>Отель 4★ и личный трансфер</div><a class="bkxb-btn" data-lead href="#contacts">Забронировать</a></div></div></div></section>'

FAQ_ITEMS=[('Что входит в стоимость тура?','Проезд на нашем автобусе, проживание, питание, экскурсии по программе и сопровождение организаторов на всём маршруте.'),('Нужна ли специальная физическая подготовка?','Нет, маршрут комфортный и подходит для новичков. Все активности по желанию, темп подстраиваем под группу.'),('Какие вещи взять с собой?','После бронирования пришлём подробный список: тёплые вещи, удобная обувь и хорошее настроение обязательны :)'),('Есть ли рассрочка?','Да, оплату можно разбить на удобные части. Напишите нам, и мы подберём комфортный вариант.')]
faq_cards=''.join(f'<div class="bkx-acc"><button class="bkx-acc__q">{q}</button><div class="bkx-acc__a"><div>{a}</div></div></div>' for q,a in FAQ_ITEMS)
FAQ=f'<section class="bkx bkx-faq"><div class="bkx-faq__wrap"><span class="bkx-chip">FAQ</span><h2>Ответы на вопросы</h2><div class="bkx-acc-list" id="bkxAcc">{faq_cards}</div></div></section>'

FINAL='<section class="bkxb bkxb-final" id="contacts"><div class="bkxb-wrap"><div class="bkxb-final__box"><div><h2>Давайте начнём следующую неделю на Байкале?</h2><p>Оставьте заявку, и мы вместе подберём удобное время для вашего отпуска.</p><button class="bkxb-btn" data-lead>Забронировать</button></div><div class="bkxb-final__pics"><div class="bkxb-fp2" style="background-image:url(assets/f16e6edf57d3662f.jpg)"></div><div class="bkxb-fp1" style="background-image:url(assets/bad4cb33b508c95f.jpg)"></div><div class="bkxb-fp3" style="background-image:url(assets/21567c9f607ea09c.jpg)"></div></div></div><div class="bkxb-final__contacts"><a href="mailto:hello@davaivput.ru">hello@davaivput.ru</a><a href="tel:+79179999999">+7 917 999 99 99</a><span>Москва, ул. Пушкина, дом 33</span></div></div></section>'

MODAL='''<div class="bkx-modal" id="bkxLead"><div class="bkx-modal__box"><button class="bkx-modal__x" data-close>&times;</button><div id="bkxLeadForm"><h3>Оставьте заявку</h3><p>Перезвоним, подберём даты и ответим на все вопросы.</p><input type="text" placeholder="Ваше имя"><input type="tel" placeholder="+7 (___) ___-__-__"><button class="bkxb-btn" data-submit>Отправить заявку</button></div><div id="bkxLeadOk" style="display:none;text-align:center;padding:14px 0"><svg viewBox="0 0 24 24" width="58" height="58" fill="none" stroke="#5860ed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px"><circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.5 2.5L16 9"/></svg><h3>Заявка отправлена!</h3><p>Спасибо! Мы свяжемся с вами в ближайшее время.</p></div></div></div>'''

SCRIPTS='''<script>
document.querySelectorAll('#bkxAcc .bkx-acc__q').forEach(function(q){q.addEventListener('click',function(){var acc=q.parentElement,a=q.nextElementSibling,open=acc.classList.contains('open');document.querySelectorAll('#bkxAcc .bkx-acc').forEach(function(x){x.classList.remove('open');x.querySelector('.bkx-acc__a').style.maxHeight=null;});if(!open){acc.classList.add('open');a.style.maxHeight=a.scrollHeight+'px';}});});
document.querySelectorAll('.bkxh__nav a').forEach(function(a){a.addEventListener('click',function(){this.closest('.bkxh__nav').classList.remove('open');});});
(function(){var m=document.getElementById('bkxLead'),f=document.getElementById('bkxLeadForm'),ok=document.getElementById('bkxLeadOk');function op(e){if(e)e.preventDefault();f.style.display='block';ok.style.display='none';m.classList.add('open');document.body.style.overflow='hidden';}function cl(){m.classList.remove('open');document.body.style.overflow='';}document.querySelectorAll('[data-lead]').forEach(function(b){b.addEventListener('click',op);});m.addEventListener('click',function(e){if(e.target===m||e.target.hasAttribute('data-close'))cl();});document.querySelector('[data-submit]').addEventListener('click',function(){f.style.display='none';ok.style.display='block';});document.addEventListener('keydown',function(e){if(e.key==='Escape')cl();});})();
</script>'''

REPL={'rec1317587861':HEADER,'rec1317644991':HERO,'rec1317763671':STATEMENT,'rec1317823931':TALCY,'rec1317864421':SHAMAN,'rec1318079321':ZHD_INTRO,'rec1318120791':ZHD_CARDS,'rec1337301151':ORG,'rec1337400061':'','rec1337398831':PAST,'rec1337500571':STEPS,'rec1318619841':REVIEWS,'rec1319018671':TAR,'rec1319097571':FAQ,'rec1319105141':'','rec1319118311':FINAL}

parts=re.split(r'(?=<div id="rec\d+")',s)
head=parts[0]; recs=parts[1:]
# tail from last record chunk
last=recs[-1]
m=re.search(r'((?:</div>\s*)*<script>[^<]*?data-tu-lazy.*)$', last, flags=re.S)
tail=m.group(1) if m else '</div></body></html>'

out=[head+MASTER_CSS]
for r in recs:
    rid=re.match(r'<div id="(rec\d+)"',r).group(1)
    if rid in DROP: continue
    if rid in REPL: out.append(REPL[rid])
    else: out.append(r)  # keep hero-text, tour band, advantages
res=''.join(out).rstrip()
# ensure tail present (last real record rec1319118311 replaced by FINAL -> append tail)
res=res+'\n'+MODAL+SCRIPTS+'\n'+tail
need=res.count('<div')-res.count('</div>')
if need>0:
    mm=re.search(r'<script>[^<]*?data-tu-lazy',res) or re.search(r'</body>',res)
    res=res[:mm.start()]+('</div>'*need)+res[mm.start():]
if '</body>' not in res: res+='</body>'
if '</html>' not in res: res+='</html>'

# write into fresh dir then swap
open(SRC,'w',encoding='utf-8').write(res)  # write into fresh
# ensure img/ present in fresh
if not os.path.isdir('_mirror/01-baikal-fresh/img'):
    shutil.copytree('01-baikal/img','_mirror/01-baikal-fresh/img')

print('div balance',res.count('<div'),res.count('</div>'))
print('body/html',('</body>' in res),('</html>' in res))
print('sections bkx:',res.count('class="bkx'),'header:',('bkxh__logo' in res))
print('junk gone:',all(j not in res for j in ['Tilda Publishing','Бесплатный курс','hello@example']))
print('zoomer hidden:','.t-zoomer__wrapper,.t-zoomer,' in res)
print('kept totest:', all(k in res for k in ['rec1317564931','rec1318173711','rec1318583181']))
