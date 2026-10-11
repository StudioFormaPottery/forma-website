import math
# Maße in mm. Kürbis-Außenform als Vereinigung von 5 Ellipsen + Stiel, radial abgetastet vom Mittelpunkt.
LOBES=[(0,0,46,70),(-36,2,42,64),(36,2,42,64),(-62,6,30,52),(62,6,30,52)]
STEM=[(-9,-60),(-9,-74),(-7,-82),(-3,-87),(3,-89),(8,-86),(10,-79),(9,-60)]  # leicht schräg, y nach oben negativ
def ell_hit(c,s,cx,cy,rx,ry):
    a=(c/rx)**2+(s/ry)**2; b=-2*(c*cx/rx**2+s*cy/ry**2); cc=(cx/rx)**2+(cy/ry)**2-1
    d=b*b-4*a*cc
    if d<0: return None
    return (-b+math.sqrt(d))/(2*a)
def poly_hit(c,s,poly):
    best=None
    for i in range(len(poly)):
        x1,y1=poly[i];x2,y2=poly[(i+1)%len(poly)]
        dx,dy=x2-x1,y2-y1;den=c*dy-s*dx
        if abs(den)<1e-9: continue
        t=(x1*dy-y1*dx)/den; u=(x1*s-y1*c)/den
        u=((x1)*s-(y1)*c)/den
        # solve t*c = x1+u*dx, t*s=y1+u*dy
        u=(t*c-x1)/dx if abs(dx)>1e-9 else (t*s-y1)/dy
        if t>0 and -1e-9<=u<=1+1e-9: best=max(best or 0,t)
    return best
def outline(stem=True,n=900):
    pts=[]
    for i in range(n):
        th=2*math.pi*i/n;c,s=math.cos(th),math.sin(th)
        r=max(t for t in [ell_hit(c,s,*L) for L in LOBES] if t)
        if stem:
            h=poly_hit(c,s,STEM)
            if h: r=max(r,h)
        pts.append((r*c,r*s))
    return pts
def bbox(pts):
    xs=[p[0] for p in pts];ys=[p[1] for p in pts];return min(xs),min(ys),max(xs),max(ys)
def path(pts,sx=1,sy=1,ox=0,oy=0):
    return 'M'+' L'.join(f'{x*sx+ox:.2f} {y*sy+oy:.2f}' for x,y in pts)+'Z'
OUT=outline(True);BODY=outline(False)
x0,y0,x1,y1=bbox(OUT);bx0,by0,bx1,by1=bbox(BODY)
RIM=18  # Randbreite mm
SX=((bx1-bx0)-2*RIM)/(bx1-bx0); SY=((by1-by0)-2*RIM)/(by1-by0)
INNER=[(x*SX,y*SY+ (by0+by1)/2*(1-SY)) for x,y in BODY]
# Täler (Einbuchtungen) zwischen den Lappen, für Rillen
VALLEYS=[]
for i in range(len(BODY)):
    r=lambda p: math.hypot(*p)
    a,b,c=BODY[i-1],BODY[i],BODY[(i+1)%len(BODY)]
    if r(b)<r(a) and r(b)<r(c): VALLEYS.append(b)
LEAF='M0 0 C 14 -16 40 -20 58 -6 C 44 10 18 14 0 0 Z'
LEAF_VEIN='M2 -1 C 20 -6 38 -8 54 -6 M18 -5 L 26 -13 M18 -5 L 27 3 M34 -7 L 42 -14 M34 -7 L 42 1'
if __name__=='__main__':
    print('Außen',round(x1-x0),'x',round(y1-y0),'mm; Körper',round(bx1-bx0),'x',round(by1-by0),'Täler',len(VALLEYS))
