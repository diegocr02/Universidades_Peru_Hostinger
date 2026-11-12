"""Materializa problemas originales en JSON. No se ejecuta al rendir un examen.

El JSON es la fuente de importación. Se conservan los 230 registros base y se
añaden 80 problemas matemáticos por área C/E, con claves exactas y figuras SVG.
"""
from pathlib import Path
from fractions import Fraction as F
from math import comb, isqrt, sqrt
import json
import html
import hashlib
import random

ROOT = Path(__file__).resolve().parents[1]
LETTERS = 'ABCDE'
MATH = ['Aritmética', 'Álgebra', 'Geometría', 'Trigonometría', 'Habilidad Matemática']

def num(v):
    v = F(v)
    return str(v.numerator) if v.denominator == 1 else f'{v.numerator}/{v.denominator}'

def label(x, y, value, size=18, color='#173d70', anchor='middle'):
    return f'<text x="{x}" y="{y}" font-size="{size}" fill="{color}" text-anchor="{anchor}">{html.escape(str(value))}</text>'

def line(x1, y1, x2, y2, dashed=False):
    return f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="#173d70" stroke-width="2"'+(' stroke-dasharray="6 5"' if dashed else '')+'/>'

def svg(content, title):
    return {'svg': '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="320" viewBox="0 0 600 320" role="img"><title>'+html.escape(title)+'</title><rect width="600" height="320" rx="16" fill="#f5f8fc"/><g font-family="Arial,sans-serif">'+content+'</g></svg>', 'descripcion': title}

def grid(n, m, points=False):
    step = min(52, 220/max(n, m)); ox=(600-n*step)/2; oy=270
    c=''
    for x in range(n+1): c+=line(ox+x*step,oy,ox+x*step,oy-m*step)
    for y in range(m+1): c+=line(ox,oy-y*step,ox+n*step,oy-y*step)
    c+=label(ox-18,oy+20,'O')+label(ox+n*step+20,oy-m*step,'D')
    if points:
        for x,y,t in [(2,2,'P'),(n-1,m-1,'Q')]:
            c+=f'<circle cx="{ox+x*step}" cy="{oy-y*step}" r="6" fill="#9a2638"/>'+label(ox+x*step+16,oy-y*step-10,t)
    c+=label(300,305,f'{n} columnas y {m} filas',16)
    return svg(c,'Retícula de '+str(n)+' columnas y '+str(m)+' filas'+(' con puntos P y Q' if points else ''))

def triangle(base, height, hyp=None, theta=False):
    b=float(base); h=float(height) if str(height).isdigit() else sqrt(float(hyp)**2-b*b)
    scale=220/max(b,h);w=b*scale;hh=h*scale;x=(600-w)/2;y=270
    c=f'<path d="M{x} {y} L{x+w} {y} L{x} {y-hh} Z" fill="#e1ecf9" stroke="#173d70" stroke-width="3"/>'
    c+=f'<path d="M{x} {y-14} H{x+14} V{y}" fill="none" stroke="#173d70" stroke-width="2"/>'
    c+=label(x+w/2,300,base)+label(x-20,y-hh/2,height,17,anchor='end')+label(x+w/2+35,y-hh/2-12,hyp or 'x',17)
    if theta: c+=label(x+w-25,y-7,'θ')
    return svg(c,'Triángulo rectángulo con datos del enunciado; figura no a escala')

def bars(values, names, title):
    c=''; maxv=max(values)
    for i,(v,name) in enumerate(zip(values,names)):
        x=85+i*145; h=175*v/maxv
        c+=f'<rect x="{x}" y="{255-h}" width="80" height="{h}" rx="5" fill="'+(['#173d70','#d4a017','#9a2638'][i%3])+'"/>'
        c+=label(x+40,240-h,v)+label(x+40,285,name,15)
    return svg(label(300,30,title,18)+c,title)

def add(area, subject, family, i, statement, answer, solution, params, image=None, unit='', difficulty='intermedio'):
    answer=F(answer)
    distract=[answer+1,answer-1,answer+2,answer*2,answer/2,answer+3,answer-2,answer+5]
    vals=[answer]
    for v in distract:
        if v not in vals and v>=0: vals.append(v)
        if len(vals)==5: break
    assert len(vals)==5
    code=f'SM-{area}-{subject_codes[subject]}-{family[:3].upper()}-{i+1:02}'
    rng=random.Random(int(hashlib.sha256(code.encode()).hexdigest(),16));rng.shuffle(vals)
    options={l:num(v)+unit for l,v in zip(LETTERS,vals)}
    p={'codigo':code,'asignatura':subject,'areas':[area],'origen':'original_simulador',
       'tema':family.replace('_',' '),'nivel':difficulty,'enunciado':statement,
       'alternativas':options,'correcta':LETTERS[vals.index(answer)],'rubrica':None,
       'solucion':solution,'imagen':image,
       'verificacion':{'familia':family,'parametros':params,'respuesta_exacta':num(answer),'unidad':unit}}
    NEW.append(p)

subject_codes={'Aritmética':'ARI','Álgebra':'ALG','Geometría':'GEO','Trigonometría':'TRI','Habilidad Matemática':'HM'}
NEW=[]
for area in ['C','E']:
    offset=0 if area=='C' else 5
    for i in range(4):
        k=i+offset+1
        # Aritmética: porcentajes sucesivos, conjuntos, divisores y promedios.
        V=100+20*k; r=V//5; initial=50+10*k; ans=F(initial)*F(4,5)**2
        pic=svg('<rect x="90" y="95" width="110" height="160" rx="8" fill="#dceaf9" stroke="#173d70"/>'+label(145,80,f'{V} L')+label(145,170,f'{initial} g de sal',16)+line(215,170,365,170)+label(290,140,f'Extraer {r} L',16)+label(290,200,'Reponer agua pura',16)+'<rect x="395" y="95" width="110" height="160" rx="8" fill="#eef4fb" stroke="#173d70"/>'+label(450,170,'Repetir 2 veces',16),'Proceso de extracción y reposición de una mezcla homogénea')
        add(area,'Aritmética','mezclas',i,f'Un recipiente contiene {V} L de solución homogénea con {initial} g de sal. Se extraen {r} L y se reponen con agua pura hasta el volumen inicial. Se mezcla y se repite el procedimiento una vez más. ¿Cuántos gramos de sal quedan?',ans,f'En cada extracción permanece la fracción ({V} − {r})/{V} = 4/5 de la sal. Después de dos operaciones quedan {initial} × (4/5)² = {num(ans)} g.',{'V':V,'r':r,'sal':initial},pic,' g')
        n=100+10*k; a=55+5*k; b=45+3*k; neither=10+k; both=a+b-(n-neither); ans=a+b-2*both
        pic=svg('<circle cx="235" cy="160" r="85" fill="#dceaf9" stroke="#173d70"/><circle cx="355" cy="160" r="85" fill="#f5d978" fill-opacity="0.6" stroke="#9a2638"/>'+label(205,165,'M')+label(385,165,'L')+label(300,35,f'Total: {n} estudiantes')+label(300,290,f'Ninguno: {neither}',17),'Conjuntos de estudiantes que participan en Matemática y Lenguaje')
        add(area,'Aritmética','conjuntos',i,f'De {n} estudiantes, {a} participan en Matemática y {b} en Lenguaje; {neither} no participan en ninguno. ¿Cuántos participan exactamente en uno de los dos talleres?',ans,f'La unión tiene {n-neither} integrantes. La intersección es {a}+{b}−{n-neither}={both}. Exactamente uno: {a}+{b}−2({both})={ans}.',{'n':n,'a':a,'b':b,'ninguno':neither},pic)
        a=4+2*k; b=2+2*k; N=2**a*3**b; ans=(a//2)*(b//2)
        add(area,'Aritmética','divisores',i,f'Un número N se expresa como 2^{a} × 3^{b}. ¿Cuántos divisores positivos de N son cuadrados perfectos y, a la vez, múltiplos de 36?',ans,f'Un divisor cuadrado tiene exponentes pares. Para ser múltiplo de 36, ambos exponentes deben ser al menos 2. Hay {a//2} elecciones para el exponente de 2 y {b//2} para el de 3; producto: {ans}.',{'a':a,'b':b})
        low=8+k; average=low+4; ans=low+6
        add(area,'Aritmética','promedios',i,f'Tres grupos de 10, 20 y 30 estudiantes rindieron una prueba sobre 100 puntos y obtuvieron promedios de {low}, {low+3} y x, respectivamente. El promedio conjunto fue {average}. ¿Cuál fue el promedio del tercer grupo?',ans,f'El promedio es ponderado: (10×{low}+20×{low+3}+30x)/60={average}. Así, 30x={60*average-10*low-20*(low+3)} y x={ans}.',{'n':[10,20,30],'promedios':[low,low+3],'total':average},bars([10,20,30],['Grupo 1','Grupo 2','Grupo 3'],'Cantidad de estudiantes por grupo'))
        # Álgebra: relaciones entre raíces, logaritmos, inecuaciones y composición.
        r0=2+k; s0=5+k; S=r0+s0; P=r0*s0; ans=r0**3+s0**3
        add(area,'Álgebra','raices',i,f'Las raíces α y β de x² − {S}x + {P} = 0 representan dos parámetros de un modelo. Sin resolver individualmente las raíces, halle α³ + β³.',ans,f'α+β={S} y αβ={P}. La identidad α³+β³=(α+β)³−3αβ(α+β) da {S}³−3×{P}×{S}={ans}.',{'suma':S,'producto':P},difficulty='avanzado')
        a=1+k; b=a+6; ans=a+8
        add(area,'Álgebra','logaritmos',i,f'Para x real, resuelva log₂(x − {a}) + log₂(x − {b}) = 4. ¿Cuál es el único valor admisible de x?',ans,f'El dominio exige x>{b}. Al reunir los logaritmos: (x−{a})(x−{b})=16. Las raíces son {a+8} y {a-2}; únicamente {a+8} pertenece al dominio.',{'a':a,'b':b,'potencia':16})
        a=k; c=k+3; b=k+7; lower=a-2; upper=b+2
        admissible=[x for x in range(lower,upper+1) if x!=c and F((x-a)*(x-b),x-c)>0];ans=len(admissible)
        pic=svg(line(60,160,540,160)+''.join(line(x,150,x,170)+label(x,200,t) for x,t in [(90,str(lower)),(180,str(a)),(290,str(c)),(420,str(b)),(510,str(upper))])+label(300,80,f'(x − {a})(x − {b}) / (x − {c}) > 0',22),'Puntos críticos y extremos del intervalo para analizar los signos; figura no a escala')
        add(area,'Álgebra','inecuaciones',i,f'¿Cuántos valores enteros x del intervalo [{lower}, {upper}] satisfacen (x − {a})(x − {b})/(x − {c}) > 0?',ans,f'Los puntos críticos son {a}, {c} y {b}. El cociente es positivo en ({a},{c}) y ({b},+∞), con x≠{c}. En el intervalo pedido: '+', '.join(map(str,admissible))+f'. Son {ans} enteros.',{'a':a,'b':b,'c':c,'inferior':lower,'superior':upper},pic,difficulty='avanzado')
        a=2+k; b=1+k; c=3+k; x=2; ans=(a*x+b)**2-c-(a*(x*x-c)+b)
        pic=svg('<rect x="65" y="110" width="180" height="75" rx="12" fill="#dceaf9" stroke="#173d70"/>'+label(155,155,f'f(x)={a}x+{b}',21)+line(260,148,340,148)+'<rect x="355" y="110" width="180" height="75" rx="12" fill="#fff3d3" stroke="#d4a017"/>'+label(445,155,f'g(x)=x²−{c}',21)+label(300,250,'El orden de las transformaciones importa',18),'Dos funciones para comparar su composición en ambos órdenes')
        add(area,'Álgebra','composicion',i,f'Sean f(x)={a}x+{b} y g(x)=x²−{c}. Halle g(f(2)) − f(g(2)).',ans,f'f(2)={2*a+b}; g(f(2))={(2*a+b)**2-c}. Por otro lado g(2)={4-c} y f(g(2))={a*(4-c)+b}. La diferencia es {ans}.',{'a':a,'b':b,'c':c,'x':x},pic)
        # Geometría: relaciones métricas, semejanza, potencia de punto y distancia.
        u=9*k;v=16*k;ans=150*k*k
        pic=svg('<path d="M150 250 L450 250 L258 106 Z" fill="#e1ecf9" stroke="#173d70" stroke-width="3"/><path d="M250.8 115.6 L260.4 122.8 L267.6 113.2" fill="none" stroke="#173d70" stroke-width="2"/>'+line(258,106,258,250,True)+label(200,280,f'{u} cm')+label(354,280,f'{v} cm')+label(278,180,'h')+label(258,86,'A')+label(132,255,'B')+label(470,255,'C')+label(258,275,'H',14),'Triángulo rectángulo en A y altura AH a la hipotenusa BC')
        add(area,'Geometría','altura',i,f'En el triángulo ABC, rectángulo en A, la altura AH divide la hipotenusa BC en BH={u} cm y HC={v} cm. Halle el área del triángulo ABC.',ans,f'Por relaciones métricas, AH²=BH×HC={u}×{v}; AH={12*k} cm. Luego área=BC×AH/2={25*k}×{12*k}/2={ans} cm².',{'u':u,'v':v},pic,' cm²')
        b=6*k;B=12*k;h=9*k;ans=F(b*b*h,2*(B+b))
        pic=svg('<path d="M120 250 L480 250 L390 70 L210 70 Z" fill="#fff" stroke="#173d70" stroke-width="3"/><path d="M210 70 L390 70 L300 130 Z" fill="#efd273"/>'+line(120,250,390,70)+line(480,250,210,70)+label(300,50,f'AB={b} cm')+label(300,285,f'CD={B} cm')+label(530,170,f'h={h} cm',16)+label(300,155,'O')+label(195,72,'A',15)+label(405,72,'B',15)+label(495,255,'C',15)+label(105,255,'D',15),'Trapecio ABCD con AB paralelo a CD, diagonales en O y triángulo AOB sombreado')
        add(area,'Geometría','semejanza',i,f'En un trapecio ABCD, AB y CD son paralelos y miden {b} cm y {B} cm. La altura del trapecio es {h} cm y sus diagonales se cortan en O. Halle el área sombreada del triángulo AOB.',ans,f'Por semejanza, la distancia de O a AB es h×AB/(AB+CD)={h}×{b}/{B+b}={num(F(h*b,B+b))}. Área AOB=AB×esa altura/2={num(ans)} cm².',{'b':b,'B':B,'h':h},pic,' cm²',difficulty='avanzado')
        a=4*k;ab=5*k;ans=6*k
        pic=svg('<circle cx="288" cy="180" r="80" fill="#e1ecf9" stroke="#173d70" stroke-width="2"/>'+line(80,180,368,180)+line(80,180,257.23,106.15)+label(62,185,'P')+label(207,205,'A')+label(380,185,'B')+label(258,90,'T')+label(145,210,f'{a} cm',16)+label(288,210,f'{ab} cm',16)+label(154,120,'x',20),'Desde P salen una secante PAB y una tangente PT a la circunferencia; figura no a escala')
        add(area,'Geometría','tangente',i,f'Desde un punto exterior P se trazan la tangente PT y la secante PAB a una circunferencia, con A entre P y B. Si PA={a} cm y AB={ab} cm, halle PT.',ans,f'PB=PA+AB={a+ab}. Por potencia de P, PT²=PA×PB={a}×{a+ab}={a*(a+ab)}. PT={ans} cm.',{'pa':a,'ab':ab},pic,' cm')
        c=10*k;ans=4*k*k
        pic=svg(line(80,260,520,260)+line(140,290,140,35)+line(140,65,420,260)+label(145,285,'O')+label(520,285,'x')+label(120,35,'y')+label(330,125,f'3x + 4y = {c}',18)+'<circle cx="140" cy="260" r="60" fill="none" stroke="#9a2638" stroke-width="2"/>'+label(360,305,'Esquema referencial, no a escala',14),'Circunferencia con centro en el origen y recta tangente; esquema no a escala')
        # El esquema muestra la relación, no una escala de coordenadas.
        add(area,'Geometría','analitica',i,f'Una circunferencia tiene centro en O(0,0) y es tangente a la recta 3x+4y={c}. Si su área es Kπ unidades cuadradas, halle K.',ans,f'El radio es la distancia del origen a la recta: r=|−{c}|/√(3²+4²)={2*k}. Área=πr²={ans}π; K={ans}.',{'c':c},None)
        # Trigonometría: alturas, identidades, ley de cosenos y ángulo mitad.
        height=6+3*k;ans=height
        pic=svg(line(150,250,540,250)+line(500,70,500,250)+line(500,70,188.23,250)+line(500,70,320,250)+label(188,278,'A')+label(320,278,'B')+label(520,165,'h')+label(235,242,'30°',16)+label(357,242,'45°',16)+label(280,307,f'AB={height}(√3−1) m',16),'Observadores A y B en terreno horizontal ven la cima de una torre bajo 30 y 45 grados')
        add(area,'Trigonometría','elevacion',i,f'Dos observadores A y B están alineados con el pie de una torre, en terreno horizontal y al mismo lado. B está más cerca. Los ángulos de elevación son 30° desde A y 45° desde B. Si AB={height}(√3−1) m, ¿qué altura tiene la torre? Desprecie la altura de los observadores.',ans,f'Si la altura es h, las distancias al pie desde A y B son h√3 y h. Entonces AB=h(√3−1)={height}(√3−1), por lo que h={height} m.',{'h':height},pic,' m')
        r0=2+k;s0=3+k;ans=F((r0+s0)**2,r0*r0+s0*s0)
        add(area,'Trigonometría','identidades',i,f'Si θ es un ángulo agudo y tan θ={r0}/{s0}, halle (sen θ+cos θ)².',ans,f'En un triángulo proporcional, los catetos son {r0} y {s0}. Así, (sen θ+cos θ)²=({r0}+{s0})²/({r0}²+{s0}²)={num(ans)}.',{'r':r0,'s':s0},triangle(str(s0),str(r0),'hipotenusa',True))
        a=5*k;b=8*k;ans=7*k
        pic=svg('<path d="M130 260 L255 260 L230 86.795 Z" fill="#e1ecf9" stroke="#173d70" stroke-width="3"/>'+label(193,290,f'{a} cm')+label(155,165,f'{b} cm')+label(275,170,'x')+label(164,245,'60°',16),'Triángulo con dos lados conocidos y ángulo comprendido de 60 grados')
        add(area,'Trigonometría','cosenos',i,f'Dos lados de un triángulo miden {a} cm y {b} cm y forman un ángulo de 60°. ¿Cuánto mide el lado opuesto a ese ángulo?',ans,f'Ley de cosenos: x²={a}²+{b}²−2×{a}×{b}×cos60°={49*k*k}. Por tanto x={ans} cm.',{'a':a,'b':b},pic,' cm')
        triples = [(3,4,5),(5,12,13),(8,15,17),(7,24,25)] if area=='C' else [(20,21,29),(9,40,41),(12,35,37),(11,60,61)]
        a,b,c=triples[i];ans=F(b,c+a)
        add(area,'Trigonometría','angulo_mitad',i,f'Si θ es agudo y cos θ={a}/{c}, halle tan(θ/2).',ans,f'Como θ es agudo, sen θ=√(1−({a}/{c})²)={b}/{c}. La identidad tan(θ/2)=senθ/(1+cosθ) produce {b}/({c}+{a})={num(ans)}.',{'a':a,'c':c},triangle(str(a),'cateto',str(c),True))
        # Habilidad matemática: combinatoria, probabilidad, trabajo y conteo visual.
        n=4+k;m=3+(k%3);p=(2,2);q=(n-1,m-1)
        total=comb(4,2)*comb(n+m-4,n-2);blocked=comb(4,2)*comb(q[0]+q[1]-4,q[0]-2)*2;ans=total-blocked
        add(area,'Habilidad Matemática','caminos',i,f'En una retícula de {n} columnas y {m} filas se va de O(0,0) a D({n},{m}) avanzando solo una unidad a la derecha o hacia arriba. ¿Cuántos caminos pasan por P(2,2) y no pasan por Q({q[0]},{q[1]})?',ans,f'Por P: C(4,2)×C({n+m-4},{n-2})={total}. Por P y Q: C(4,2)×C({q[0]+q[1]-4},{q[0]-2})×C(2,1)={blocked}. Al restar quedan {ans}.',{'n':n,'m':m,'p':list(p),'q':list(q)},grid(n,m,True),difficulty='avanzado')
        r0=4+k;b=3+k;ans=F(comb(r0,2)*b,comb(r0+b,3))
        pic=svg('<path d="M155 75 Q110 255 200 270 H400 Q490 255 445 75 Z" fill="#eaf1fa" stroke="#173d70" stroke-width="3"/>'+label(300,145,f'{r0} rojas',24,'#9a2638')+label(300,195,f'{b} azules',24)+label(300,300,'Se extraen 3 sin reposición',17),'Urna con cantidades de bolas rojas y azules indicadas')
        add(area,'Habilidad Matemática','probabilidad',i,f'Una urna contiene {r0} bolas rojas y {b} azules, indistinguibles al tacto. Se extraen tres simultáneamente al azar. ¿Cuál es la probabilidad de obtener exactamente dos rojas?',ans,f'Casos favorables: C({r0},2)×C({b},1)={comb(r0,2)*b}. Casos posibles: C({r0+b},3)={comb(r0+b,3)}. Probabilidad={num(ans)}.',{'rojas':r0,'azules':b},pic)
        a=12+3*k;b=18+3*k;t=3;ans=(1-F(t,a)-F(t,b))*a
        pic=svg(label(300,55,'Una obra completa',23)+'<rect x="80" y="100" width="440" height="55" fill="#eaf1fa" stroke="#173d70"/><rect x="80" y="100" width="160" height="55" fill="#e7c860"/>'+label(160,135,'A + B',20)+label(380,135,'Solo A',20)+label(160,195,f'{t} días juntos',17)+label(380,195,'Días adicionales: x',17)+label(300,265,'El ancho de los bloques no está a escala',15),'Dos etapas de trabajo: primero juntos y luego únicamente A')
        add(area,'Habilidad Matemática','trabajo',i,f'A completa una obra en {a} días y B en {b} días, trabajando solos a ritmo constante. Trabajan juntos durante {t} días y luego B se retira. ¿Cuántos días adicionales necesita A para terminar?',ans,f'En {t} días realizan {t}(1/{a}+1/{b})={num(F(t,a)+F(t,b))} de la obra. Lo restante es {num(1-F(t,a)-F(t,b))}. A tarda {a}×{num(1-F(t,a)-F(t,b))}={num(ans)} días adicionales.',{'a':a,'b':b,'t':t},pic,' días')
        n=3+i;m=4+i+offset;squares=sum((n-j+1)*(m-j+1) for j in range(1,min(n,m)+1));rects=comb(n+1,2)*comb(m+1,2);ans=rects-squares
        add(area,'Habilidad Matemática','rectangulos',i,f'La figura es una retícula de {n} columnas y {m} filas de cuadrados iguales. ¿Cuántos rectángulos con lados sobre las líneas de la retícula no son cuadrados?',ans,f'Todos los rectángulos: C({n+1},2)×C({m+1},2)={rects}. Cuadrados de todos los tamaños: Σ({n}−j+1)({m}−j+1)={squares}. Diferencia={ans}.',{'n':n,'m':m},grid(n,m),difficulty='avanzado')

def verify(p):
    """Recalcula por métodos alternativos antes de publicar una clave en JSON."""
    v=p['verificacion']; d=v['parametros']; f=v['familia'];result=None
    if f=='mezclas':
        mass=F(d['sal'])
        for _ in range(2): mass-=mass*d['r']/d['V']
        result=mass
    elif f=='conjuntos':
        both=d['a']+d['b']+d['ninguno']-d['n'];result=(d['a']-both)+(d['b']-both)
    elif f=='divisores':
        result=sum(1 for a in range(d['a']+1) for b in range(d['b']+1) if a%2==0 and b%2==0 and a>=2 and b>=2)
    elif f=='promedios': result=F(sum(d['n'])*d['total']-sum(n*a for n,a in zip(d['n'],d['promedios'])),d['n'][2])
    elif f=='raices':
        disc=isqrt(d['suma']**2-4*d['producto']);r=F(d['suma']-disc,2);s=F(d['suma']+disc,2);result=r**3+s**3
    elif f=='logaritmos':
        roots=[x for x in range(max(d['a'],d['b'])+1,100) if (x-d['a'])*(x-d['b'])==d['potencia']];assert len(roots)==1;result=roots[0]
    elif f=='inecuaciones': result=sum(1 for x in range(d['inferior'],d['superior']+1) if x!=d['c'] and (x-d['a'])*(x-d['b'])*(x-d['c'])>0)
    elif f=='composicion':
        f1=lambda x:d['a']*x+d['b'];g=lambda x:x*x-d['c'];result=g(f1(d['x']))-f1(g(d['x']))
    elif f=='altura': result=F((d['u']+d['v'])*isqrt(d['u']*d['v']),2)
    elif f=='semejanza':
        # Área total y razón de semejanza de las diagonales.
        total=F((d['b']+d['B'])*d['h'],2);result=total*F(d['b'],d['b']+d['B'])**2
    elif f=='tangente': result=isqrt(d['pa']*(d['pa']+d['ab']));assert result**2==d['pa']*(d['pa']+d['ab'])
    elif f=='analitica': result=F(d['c']**2,25)
    elif f=='elevacion': result=d['h'] # h(√3−1)=dato(√3−1).
    elif f=='identidades': result=1+F(2*d['r']*d['s'],d['r']**2+d['s']**2)
    elif f=='cosenos': result=isqrt(d['a']**2+d['b']**2-d['a']*d['b'])
    elif f=='angulo_mitad':
        a,c=d['a'],d['c'];result=F(c-a,isqrt(c*c-a*a))
    elif f=='caminos':
        # Programación dinámica con estado de haber visitado P y prohibición de Q.
        dp={(0,0,False):1}
        for total in range(d['n']+d['m']+1):
            for x in range(d['n']+1):
                y=total-x
                if not 0<=y<=d['m']:continue
                for seen in [False,True]:
                    count=dp.get((x,y,seen),0)
                    for nx,ny in [(x+1,y),(x,y+1)]:
                        if nx>d['n'] or ny>d['m'] or [nx,ny]==d['q']:continue
                        ns=seen or [nx,ny]==d['p'];dp[nx,ny,ns]=dp.get((nx,ny,ns),0)+count
        result=dp.get((d['n'],d['m'],True),0)
    elif f=='probabilidad':
        from itertools import combinations
        cases=list(combinations(range(d['rojas']+d['azules']),3));result=F(sum(sum(x<d['rojas'] for x in case)==2 for case in cases),len(cases))
    elif f=='trabajo':
        done=F(d['t'],d['a'])+F(d['t'],d['b']);result=(1-done)/F(1,d['a'])
    elif f=='rectangulos':
        result=sum(1 for x1 in range(d['n']) for x2 in range(x1+1,d['n']+1) for y1 in range(d['m']) for y2 in range(y1+1,d['m']+1) if x2-x1!=y2-y1)
    assert result is not None and F(result)==F(v['respuesta_exacta']),p['codigo']
    assert p['alternativas'][p['correcta']]==num(result)+v['unidad']
    assert len(set(p['alternativas'].values()))==5

for p in NEW: verify(p)
if __name__=='__main__':
    import argparse
    parser=argparse.ArgumentParser();parser.add_argument('--base',default=str(ROOT/'database/data/unmsm_preguntas.json'));parser.add_argument('--verificar',action='store_true');args=parser.parse_args()
    original=json.loads(Path(args.base).read_text(encoding='utf8'))
    if args.verificar:
        for p in original['preguntas']:
            if 'verificacion' in p: verify(p)
        print('Claves matemáticas del JSON verificadas por cálculo alternativo.')
        raise SystemExit(0)
    base=original if isinstance(original,list) else [p for p in original['preguntas'] if 'verificacion' not in p]
    for p in base:
        if p['asignatura'] in MATH: p['areas']=['A']
    doc={'version':1,'revision':'2026-11-12-v1','universidad':'Universidad Nacional Mayor de San Marcos',
        'naturaleza':'Problemas originales de entrenamiento. No son preguntas oficiales ni una certificación de dificultad.',
        'referencias':[{'titulo':'Clases modelo del Centro Preuniversitario UNMSM','url':'https://cepre.unmsm.edu.pe/clases-modelo/','uso':'Orientación de asignaturas, sin copiar preguntas'},
                      {'titulo':'Prueba de destrezas cognitivas DECO, reglamento UNMSM','url':'https://viceacademico.unmsm.edu.pe/wp-content/uploads/2021/03/Reglamento-de-Admisi%C3%B3n-2021-UNMSM.pdf','uso':'Enfoque de razonamiento y temario, no distribución vigente'}],
        'preguntas':base+NEW}
    path=ROOT/'database/data/unmsm_preguntas.json';path.write_text(json.dumps(doc,ensure_ascii=False,indent=2)+'\n',encoding='utf8')
    print(f'{len(doc["preguntas"])} preguntas en JSON, {len(NEW)} nuevas verificadas con claves exactas, '+str(sum(bool(p['imagen']) for p in doc['preguntas']))+' con imágenes.')
