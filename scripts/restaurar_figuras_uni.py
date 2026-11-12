"""Figuras SVG de los problemas UNI r2. No modifica enunciados ni claves."""
from pathlib import Path
from math import sin, cos, pi
from html import escape

ROOT=Path(__file__).resolve().parents[1]
DEST=ROOT/'public/images/preguntas/uni'

def text(x,y,t,size=19,color='#173d70'):
    return f'<text x="{x}" y="{y}" text-anchor="middle" fill="{color}" font-size="{size}">{escape(str(t))}</text>'
def line(x,y,a,b,dash=False,color='#173d70'):
    return f'<line x1="{x}" y1="{y}" x2="{a}" y2="{b}" stroke="{color}" stroke-width="2.5"'+(' stroke-dasharray="6 5"' if dash else '')+'/>'
def path(d,fill='none',color='#173d70',dash=False):
    return f'<path d="{d}" fill="{fill}" stroke="{color}" stroke-width="2.5"'+(' stroke-dasharray="6 5"' if dash else '')+'/>'
def circle(x,y,r,fill='none'):
    return f'<circle cx="{x}" cy="{y}" r="{r}" fill="{fill}" stroke="#173d70" stroke-width="2"/>'
def rect(x,y,w,h,fill='#e4edf9'):
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{fill}" stroke="#173d70" stroke-width="2"/>'
def polygon(points,fill='#e4edf9'):
    return f'<polygon points="{points}" fill="{fill}" stroke="#173d70" stroke-width="2.5"/>'
def frame(content,title):
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="600" height="340" viewBox="0 0 600 340" role="img"><title>{escape(title)}</title><rect width="600" height="340" rx="14" fill="#f6f9fd"/><g font-family="Arial,sans-serif">{content}</g></svg>\n'

IMAGES={}
def add(name,title,c): IMAGES[name+'.svg']=frame(c,title)

c=path('M180 70 Q150 260 220 275 H380 Q450 260 420 70 Z','#e4edf9')
for i in range(4): c+=circle(230+i*45,140,14,'#aa3043')
for i in range(6): c+=circle(210+(i%3)*80,195+(i//3)*45,14,'#4479b7')
add('ar_r2_04_urna','Urna con 4 bolas rojas y 6 azules',c+text(300,45,'4 rojas y 6 azules')+text(300,315,'Dos extracciones sin reposición',17))
c=''
for x,litros,precio,color in [(80,30,'S/ 8 por L','#8d3040'),(250,20,'S/ 13 por L','#d4a017'),(420,10,'Agua','#4479b7')]:
    c+=rect(x,100,100,145,'#fff')+f'<rect x="{x+2}" y="{245-litros*4}" width="96" height="{litros*4-2}" fill="{color}"/>'+text(x+50,80,f'{litros} L')+text(x+50,280,precio,17)
add('ar_r2_07_mezcla','Volúmenes y costos de los componentes de una mezcla',c+text(300,320,'Ganancia solicitada: 20 %',17))
c=line(90,280,540,280)+line(90,280,90,35)+line(90,70,510,280,color='#9a2638')+line(90,175,510,245,color='#2c7f78')
for i in range(1,5):c+=line(90+i*105,275,90+i*105,285)+text(90+i*105,307,i,16)
for i in range(1,5):c+=text(65,285-i*52.5,i,16)
c+=text(550,283,'x')+text(75,30,'y')+text(310,65,'x + y = 4',17,'#9a2638')+text(350,203,'x + 3y = 6',17,'#2c7f78')
add('al_r2_09_programacion_lineal','Rectas de las restricciones de programación lineal',c)
c=path('M150 250 L300 50 L450 250','#e4edf9')+'<ellipse cx="300" cy="250" rx="150" ry="23" fill="#fff3d3" stroke="#173d70" stroke-width="2"/>'+line(300,50,300,250,True)+text(277,165,'8')+text(400,145,'10')+text(300,315,'Cono circular recto',17)
add('ge_r2_01_cono','Cono de altura 8 y generatriz 10',c)
bottom=[(140,250),(360,280),(470,220),(250,190)];top=[(236,128),(324,140),(368,116),(280,104)]
c=polygon(' '.join(f'{x},{y}' for x,y in bottom),'#f7efd9')
for (x,y),(a,b) in zip(bottom,top):c+=line(x,y,a,b)
c+=polygon(' '.join(f'{x},{y}' for x,y in top),'#e4edf9')+line(510,120,510,260,True)+text(540,195,'h=6',17)+text(280,100,'4')+text(240,300,'10')
add('ge_r2_02_tronco','Tronco de pirámide regular de bases cuadradas de lados 4 y 10, y altura 6',c)
c=polygon('300,45 140,260 460,260','#e4edf9')+line(300,45,305,185)+line(305,185,140,260,True)+line(305,185,460,260)+text(205,145,'6')+text(300,310,'Todas las aristas tienen la misma longitud',16)
add('ge_r2_03_tetraedro','Tetraedro regular de arista 6',c)
c=circle(300,165,135,'#edf4fc')+polygon('220,210 350,235 395,145 265,120','none')+polygon('220,100 350,125 395,35 265,10','none')
# Proyección isométrica esquemática dentro de la esfera, conservando las conexiones.
c=circle(300,165,135,'#edf4fc')+polygon('220,220 345,245 395,155 270,130','none')+polygon('220,110 345,135 395,45 270,20','none')
# Esquema centrado: se proyectan las ocho esquinas de un cubo en su esfera circunscrita.
vertices=[(220,225),(345,250),(395,160),(270,135),(220,115),(345,140),(395,50),(270,25)]
cx=sum(v[0] for v in vertices)/8;cy=sum(v[1] for v in vertices)/8
vertices=[(300+(x-cx)*.85,165+(y-cy)*.85) for x,y in vertices]
c=circle(300,165,125,'#edf4fc')
for i,j in [(0,1),(1,2),(2,3),(3,0),(4,5),(5,6),(6,7),(7,4),(0,4),(1,5),(2,6),(3,7)]:c+=line(*vertices[i],*vertices[j],i in [2,3])
c+=line(*vertices[0],*vertices[6],True,color='#9a2638')+text(300,300,'Arista del cubo: 2',17)
add('ge_r2_04_esfera_cubo','Cubo de arista 2 y esfera circunscrita, proyección esquemática',c)
c=polygon('170,270 394,270 226,82.17')+line(226,82.17,282,270,True)+text(226,60,'A')+text(150,275,'B')+text(413,275,'C')+text(282,295,'M')+text(175,175,'7')+text(335,170,'9')+text(282,325,'BC = 8; BM = MC',17)
add('ge_r2_05_mediana','Triángulo de lados 7, 9 y 8 con mediana AM a BC',c)
c=circle(288,180,80,'#e4edf9')+line(80,180,368,180)+line(80,180,257.23,106.15)+text(60,185,'P')+text(205,210,'A')+text(385,185,'B')+text(258,90,'T')+text(145,210,'4')+text(165,118,'6')+text(290,215,'AB = ?',17)
add('ge_r2_06_tangente_secante','Tangente PT igual a 6 y secante PAB con PA igual a 4',c)
c=polygon('160,250 400,250 160,70','#fff')+circle(220,190,60,'#fff0c9')+path('M160 230 H180 V250')+text(140,165,'9')+text(290,280,'12')
add('ge_r2_07_incirculo','Triángulo rectángulo de catetos 9 y 12 con círculo inscrito',c)
c=polygon('130,270 470,270 290,45','#fff3d3')+polygon('183.33,195 410,195 290,45','#e4edf9')+text(290,30,'A')+text(110,280,'B')+text(490,280,'C')+text(165,195,'D')+text(430,195,'E')+text(202,118,'4')+text(145,240,'2')+text(294,145,'Área: 32',16)+text(310,307,'DE paralelo a BC',17)
add('ge_r2_08_semejanza','Triángulos semejantes ADE y ABC con área de ADE igual a 32',c)
points=[(300+120*cos(i*pi/3),170+120*sin(i*pi/3)) for i in range(6)]
c=polygon(' '.join(f'{x:.2f},{y:.2f}' for x,y in points),'#fff')+polygon(' '.join(f'{points[i][0]:.2f},{points[i][1]:.2f}' for i in [0,2,4]),'#e4edf9')+text(410,240,'4')+text(300,320,'Triángulo de vértices alternos',17)
add('ge_r2_09_hexagono','Hexágono regular de lado 4 y triángulo formado por vértices alternos',c)
c=circle(300,170,125,'#edf4fc')+rect(225,70,150,200,'none')+'<ellipse cx="300" cy="70" rx="75" ry="15" fill="#fff3d3" stroke="#173d70" stroke-width="2"/><ellipse cx="300" cy="270" rx="75" ry="15" fill="#fff3d3" stroke="#173d70" stroke-width="2"/>'+line(300,170,375,70,True)+text(350,135,'R=5',17)+text(412,170,'h=8',17)
add('ge_r2_10_cilindro_esfera','Cilindro de altura 8 inscrito en una esfera de radio 5',c)
c=polygon('130,260 255,260 230,86.795')+text(193,290,'5')+text(155,165,'8')+text(275,170,'7')+text(165,245,'θ')
add('tr_r2_01_ley_cosenos','Triángulo de lados 5, 7 y 8, ángulo opuesto al lado 7',c)
c=line(200,260,530,260)+line(480,138.76,480,260)+line(270,260,480,138.76)+line(410,260,480,138.76)+text(305,250,'30°',16)+text(435,247,'60°',16)+text(340,300,'20 m')+text(500,200,'h')
add('tr_r2_02_torre','Torre vista bajo 30 y 60 grados tras acercarse 20 metros',c)
c=polygon('150,270 430,270 327.51,92.49')+text(170,260,'45°',16)+text(405,260,'60°',16)+text(130,290,'A')+text(450,290,'B')+text(330,70,'C')+text(420,180,'a=10√2',16)+text(225,170,'b')
add('tr_r2_09_ley_senos','Triángulo ABC con A de 45 grados, B de 60 grados y lado a igual a 10 raíz de 2',c)
c=line(80,130,110,130)+rect(110,118,60,24)+line(170,130,220,130)+rect(220,118,60,24)+line(280,130,350,130)+line(350,60,350,200)+line(350,60,380,60)+rect(380,48,70,24)+line(450,60,500,60)+line(500,60,500,270)+line(350,200,380,200)+rect(380,188,70,24)+line(450,200,500,200)+line(500,270,80,270)+line(80,130,80,183)+line(65,183,95,183)+line(70,197,90,197)+line(80,197,80,270)+text(140,105,'r=1 Ω',16)+text(250,105,'3 Ω',16)+text(415,38,'6 Ω',16)+text(415,238,'3 Ω',16)+text(48,218,'12 V',16)
add('fi_r2_02_circuito','Batería de 12 V con resistencia interna de 1 ohm, resistencia serie de 3 ohm y paralelo de 6 y 3 ohm',c)
c=rect(40,165,520,150,'#e4edf9')+line(40,165,560,165)+line(300,35,300,310,True)+line(90,43.76,300,165,color='#9a2638')+line(300,165,510,43.76,color='#9a2638')+line(300,165,370,286.24,color='#1c6caa')+text(90,30,'Aire, n=1',16)+text(135,275,'Vidrio, n=√3',16)+text(265,100,'60°',17)+text(425,85,'Reflejado',16)+text(420,270,'Refractado',16)
add('fi_r2_03_refraccion','Rayos incidente, reflejado y refractado en la interfaz aire-vidrio con incidencia de 60 grados',c)
c=line(100,25,100,285)+line(100,220,440,220)+line(100,23.7,440,220,color='#9a2638')+circle(100,220,5,'#173d70')+line(270,220,270,290,color='#9a2638')+polygon('264,280 276,280 270,292','#9a2638')+text(310,283,'100 N',17)+text(250,105,'T')+text(395,210,'30°',16)+text(265,318,'Longitud de la viga: 2 m',17)
add('fi_r2_06_viga','Viga de 2 metros articulada a una pared, peso de 100 newtons y cable a 30 grados',c)
c=line(70,270,535,270)+path('M100 270 Q292 126 484 270',dash=True)+line(100,270,180,210,color='#9a2638')+text(160,195,'20 m/s',18)+text(145,262,'37°',16)+text(300,315,'Alcance horizontal: ?',18)+text(470,80,'g=10 m/s²',17)
add('fi_r2_08_proyectil','Trayectoria parabólica de un proyectil lanzado a 20 metros por segundo y 37 grados',c)
c=polygon('110,270 430,270 430,30','#edf1f7')+f'<g transform="translate(300 127.5) rotate(-36.87)">{rect(-25,-30,50,30,"#f0d180")}</g>'+text(300,95,'2 kg',17)+text(170,258,'37°',16)+text(215,150,'5 m',18)+text(500,175,'μ=0,25',18)+text(300,315,'Parte del reposo; g=10 m/s²',17)
add('fi_r2_12_plano_inclinado','Bloque de 2 kilogramos que desciende 5 metros por un plano de 37 grados con rozamiento',c)
c=rect(50,80,220,190,'#fff')+f'<rect x="51" y="150" width="218" height="119" fill="#dceafa"/>'+rect(120,90,70,150,'none')+line(50,150,270,150)+text(230,205,'60 %',17)+text(160,302,'Agua: 1000 kg/m³',16)
c+=rect(330,80,220,190,'#fff')+f'<rect x="331" y="127.5" width="218" height="141.5" fill="#f4e8bd"/>'+rect(400,90,70,150,'none')+line(330,127.5,550,127.5)+text(440,302,'Líquido: 800 kg/m³',16)+text(440,325,'Fracción sumergida: ?',16)
add('fi_r2_13_flotacion','Mismo cuerpo flotando en agua y en otro líquido de densidad 800 kilogramos por metro cúbico',c)
c=''
for x in range(90,551,70):
    for y in range(60,271,70):c+=line(x-4,y-4,x+4,y+4,color='#7187a2')+line(x-4,y+4,x+4,y-4,color='#7187a2')
c+=path('M140 260 A100 100 0 0 0 240 160 A100 100 0 0 0 140 60',color='#9a2638')+line(140,160,240,160,True)+text(185,145,'r')+circle(140,260,5,'#9a2638')+line(140,260,240,260)+text(285,300,'v=2×10⁶ m/s',18)+text(435,35,'B=0,5 T, hacia dentro',16)+text(100,290,'Protón',16)
add('fi_r2_16_proton_campo','Protón con velocidad perpendicular a un campo magnético de 0,5 teslas, trayectoria circular de radio r',c)
c=circle(140,160,28,'#ffe0df')+circle(460,160,28,'#ffe0df')+text(140,168,'+')+text(460,168,'+')+text(140,110,'+4 μC')+text(460,110,'+9 μC')+line(140,60,460,60)+text(300,45,'50 cm')+circle(270,160,4,'#173d70')+text(270,135,'P')+line(140,215,270,215)+line(270,215,460,215)+text(205,245,'x')+text(370,245,'0,50 − x')+text(300,300,'Campo eléctrico nulo entre las cargas',17)
add('fi_r2_17_cargas','Dos cargas positivas de 4 y 9 microculombios separadas 50 centímetros',c)

if __name__=='__main__':
    DEST.mkdir(parents=True,exist_ok=True)
    for name,content in IMAGES.items(): (DEST/name).write_text(content,encoding='utf8')
    print(f'{len(IMAGES)} figuras UNI restauradas en {DEST}')
