// node tools/movecia-dream/render.js <ausgabeordner>  (lokaler Server auf 127.0.0.1:8765 muss laufen)
const {chromium}=require('playwright');
(async()=>{const out=process.argv[2];const b=await chromium.launch();
const names={method:'Method',unfold:'Unfold',natal:'Praenatal-Postnatal',gathering:'Gathering'};
for (const p of Object.keys(names)) for (const l of ['de','en']) for (const [f,h,tag] of [['feed',1350,'Instagram-4x5'],['story',1920,'WhatsApp-Status-9x16']]){
 const pg=await b.newPage({viewport:{width:1080,height:h}});
 await pg.goto(`http://127.0.0.1:8765/tools/movecia-dream/ad.html?p=${p}&f=${f}&l=${l}`,{waitUntil:'networkidle'});
 await pg.evaluate(()=>document.fonts.ready);await pg.waitForTimeout(300);
 await pg.screenshot({path:`${out}/Movecia_${names[p]}_${tag}_${l.toUpperCase()}.png`});await pg.close();}
await b.close();})();
