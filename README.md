# Calculator Rute

Panou de control pentru calculul rentabilității curselor: introduci datele unei
curse, vezi în timp real prețul, costul, salariul șoferului, profitul și marja,
apoi salvezi ruta.

Modelul de calcul urmează foaia `Calculator_rute.xlsx`, sheet `Rute`. Rândul 2
(Hâncești → Brăila) este reprodus cifră cu cifră într-un test — vezi
`tests/Unit/RouteCostingTest.php`.

## Cum se calculează

Toată aritmetica stă într-un singur loc, `App\Support\RouteCosting`.
Calculatorul live o construiește din formular la fiecare tastă, iar o rută
salvată o construiește din propriile coloane — deci cifra de pe formular și
cifra din tabel nu pot să difere.

```
distanță totală = distanță dus + distanță întors
preț rută (EUR) = tone × preț/tonă  +  tone retur × preț/tonă retur
preț rută (MDL) = preț rută (EUR) × curs
combustibil (L) = distanță totală × consum   ← consumul e în L/km, nu L/100km
cost combustibil = combustibil × preț/L
salariu          = prima zi + (zile − 1) × zi adițională + zi încărcare
uzură            = distanță totală × uzură/km
profit           = preț rută (MDL) − combustibil − salariu − uzură − vamă − rovinietă
```

Prețul se cotează în euro și se încasează în lei; cursul e un parametru, iar
fiecare rută salvată își reține cursul cu care a fost calculată.

**Distanța are două picioare.** Camionul consumă și se uzează și la întoarcere,
fie că duce ceva, fie că vine gol — deci combustibilul și uzura se calculează pe
`dus + întors`, nu doar pe dus. Câmpul „Distanță întors" se completează singur cu
valoarea dusului, pentru că majoritatea curselor se întorc pe același drum; îl
poți edita, iar de atunci încolo rămâne cum l-ai pus. Pune 0 dacă vrei doar dusul.

Foaia sursă avea o singură coloană `Distanța (km)` și lăsa la latitudinea
cititorului dacă înseamnă un picior sau două. Aici întrebarea are răspuns: pentru
Hâncești → Brăila se introduce 220 dus, iar întorsul se completează singur tot cu
220 — cei 440 din foaie erau deja drumul întreg.

**Salariul șoferului.** Prima zi se plătește la tariful ei (2.200), fiecare zi
următoare la cel adițional (1.000). O cursă de 3 zile înseamnă 2.200 + 2 × 1.000
= 4.200. Formula e scrisă sub câmpuri, cu cifrele din casete, ca să se vadă dacă
o zi a fost numărată de două ori.

## Încărcătura de retur

Camionul nu se întoarce mereu gol. Uneori face un ocol până la un al treilea
punct, încarcă marfa altcuiva și o aduce acasă. Opțiunea e ascunsă sub un
comutator pe calculator, pentru că majoritatea curselor vin goale.

Când e pornită, adaugă trei lucruri:

- **venit** — al doilea volum, cotat tot în euro pe tonă și convertit la același
  curs.
- **o zi de încărcare** — 1.200 lei, **peste** tariful zilei, nu în locul lui.
  Ziua aceea rămâne o zi pe drum; dacă ocolul lungește cursa, crește și `Zile`.

Când e pornită, „Distanță întors" înseamnă drumul prin punctul de încărcare până
acasă, nu întoarcerea directă. Ruta se numește atunci
`Hâncești → Brăila → Galați`.

## Cheltuielile vehiculului

Fiecare camion își poartă propriile cheltuieli, în cadența în care sosesc
facturile: **asigurare, service, anvelope și alte cheltuieli** sunt anuale, iar
**GPS-ul este un abonament lunar** (400) care se înmulțește cu 12 în total.

Cifrele acestea sunt **doar o evidență** a ce costă flota. Niciuna nu intră în
calculul unei curse: uzura se reglează exclusiv din Valori implicite, un tarif fix
pentru toată flota, pentru că așa se cotează.

Motivul e practic: dacă fiecare camion și-ar impune propriul cost pe kilometru,
aceeași rută ar ieși pe alți bani în funcție de ce camion e liber, și s-ar mișca
din nou de fiecare dată când cineva corectează kilometrajul anual al unuia.

Ce preia calculatorul de la camion este **consumul**, atât.

## Sumarul

Sub totaluri stau trei lucruri, toate calculate din aceleași rute salvate
(`App\Support\FleetSummary`), cu un singur comutator de perioadă deasupra —
6 luni, 12 luni, 24 de luni sau tot istoricul.

**Venitul pe lună.** Câte o coloană pe lună: partea de jos e cât a costat cursa,
partea de sus e profitul rămas din venit. O lună pe pierdere se desenează invers
— coloana urcă până la cost, iar bucata roșie din vârf e gaura pe care venitul nu
a acoperit-o. Lunile fără curse rămân în șir, la zero. Peste doi ani de istoric
coloanele devin trimestre, apoi ani, ca să nu iasă un gard de scânduri.

O rută nu are dată proprie — singura dată de pe rând e momentul în care a fost
salvată, deci după ea se face împărțirea pe luni, iar ecranul o spune.

**Venituri per mașină.** Cât a adus fiecare camion în perioada aleasă: lungimea
barei e venitul față de cel mai încărcat camion, iar capul închis la culoare e
profitul din el. Camioanele active fără curse rămân în listă, la zero, cu
cheltuiala lor pe an alături — un camion care stă își plătește oricum asigurarea,
și exact asta merită văzut.

**Rezultatul anual.** Ce rămâne într-un an, după toate cheltuielile pe camioane:

```
venit din rute (ultimele 12 luni)
− costuri de drum      combustibil, salariu, vamă, rovinietă, alte costuri
= contribuție
− cheltuieli camioane  asigurare, service, anvelope, GPS — pe un an
= rezultat
```

**Uzura nu se scade de două ori.** Profitul unei rute conține deja
`uzură = km × uzură/km`, iar aceea *este* modul în care facturile anuale ale unui
camion se împart pe curse. De aceea linia „costuri de drum" pornește fără ea, iar
cheltuielile camioanelor intră o singură dată, întregi. Cardul arată separat cât
din ele au fost recuperate prin kilometrii parcurși — dacă procentul e mic,
camionul nu rulează destul cât să se plătească singur.

Când rutele salvate acoperă mai puțin de un an, cifra rămâne cea reală, iar sub
ea scrie la ce ar duce ritmul de până acum pe un an întreg.

## Ecrane

| Rută | Ce face |
| --- | --- |
| `/` | Sumar: totalurile, graficele pe perioade, rezultatul anual, ultimele rute |
| `/calculator` | Calculatorul live, cu rezultatul fixat lângă formular |
| `/rute` | Rutele salvate, cu căutare (localitate, camion, număr) și paginare |
| `/raport` | Darea de seamă pe o perioadă, cu filtru de camion |
| `/raport/tipar` | Aceeași foaie pe o pagină goală, care deschide tipărirea |
| `/raport/excel` | Aceleași rânduri, descărcate ca `.xlsx` |
| `/flota/vehicule` | Ce costă fiecare camion într-un an, pe categorii |
| `/flota/valori-implicite` | Parametrii cu care pornește fiecare calcul nou |

Sidebar-ul, rutele și titlurile paginilor se citesc toate din
`App\Support\DashboardNav` plus `lang/ro/dashboard.php` — o secțiune nouă
înseamnă o intrare acolo și un `view`, nimic altceva.

## Stilizare

Sistemul de design este portat din back office-ul **Hey Roger**
(`../heyrogersmith`): aceeași paletă (`forest #0e1a09`, `lime #e2ff00`,
`warm #f6f6f1`, `line #e8e8df`), aceleași raze și umbre, aceeași tipografie
(Inter), același shell — sidebar colapsabil, topbar sticky, un singur `<main>`
care derulează.

Numele tokenilor din `resources/css/app.css` sunt identice cu cele din Hey Roger,
deci o componentă poate fi mutată între cele două proiecte fără redenumiri. Ce a
rămas în urmă e partea de site public: fontul display, subsetul SF Symbols,
timeline-ul de proces și theme relay-ul per serviciu.

## Raportul

Butonul **Raport anual** de pe Sumar deschide `/raport` fix pe cele douăsprezece
luni pe care le arăta cardul „Rezultat anual", ca foaia tipărită să fie chiar
cifra de deasupra ei.

Deschis direct, raportul pornește pe **luna curentă** — cadența în care se ține
darea de seamă pe hârtie, o filă pe camion pe lună. De acolo perioada se schimbă
din cinci scurtături — luna curentă, luna trecută, anul curent, anul trecut,
ultimele 12 luni — sau din cele două câmpuri de dată, pentru orice alt interval.
Un selector alege între toată flota și un singur camion.

Foaia are un rând pe rută — data, sarcina, camionul, tonele dus și retur,
distanța, motorina, venitul, cheltuiala, profitul și marja — un rând de totaluri,
și dedesubt rezultatul perioadei:

```
venit din rute − costuri de drum = contribuție
contribuție − cheltuieli camioane (proporțional pe zilele perioadei) = rezultat
```

Ca și pe Sumar, **uzura nu se scade de două ori**: „costuri de drum" pornește
fără ea, iar facturile camioanelor intră o singură dată. Diferența e că aici sunt
împărțite pe zilele perioadei — un raport pe o lună poartă o lună de asigurare,
nu un an. Un camion cerut pe nume își poartă propriile facturi chiar dacă e
scos din uz; un raport pe toată flota le numără doar pe cele active.

**Excel.** Butonul *Excel* descarcă aceleași rânduri ca `.xlsx` — antet, o linie
pe rută, totaluri și rezultatul perioadei. Fișierul e scris de mână
(`App\Support\XlsxWriter`, un zip cu câteva XML-uri), fără bibliotecă adăugată în
proiect. Nu e un CSV pentru că un CSV n-are tipuri: sumele ies de aici cu virgulă
la zecimale, iar un Excel care nu e setat pe română le citește ca text și refuză
să le adune. Aici cifrele sunt cifre, iar felul în care se afișează — punct la
mii, virgulă la zecimale, `dd.mm.yyyy` — e format pe celulă, deci arată la fel pe
orice calculator.

**Tipărirea.** Butonul *Tipărește / PDF* deschide `/raport/tipar` într-un tab nou:
aceeași foaie pe o pagină goală, care lansează singură dialogul de tipărire. De
acolo, „Salvează ca PDF" din browser. Pagina are layout propriu
(`components/layouts/print.blade.php`) pentru că shell-ul panoului — sidebar fix,
un singur `<main>` care derulează — este exact ce nu poate un printer să înțeleagă;
foaia iese A4 landscape, cu antetul tabelului repetat pe fiecare pagină.

Coloanele din formularul pe hârtie pe care aplicația nu le are — șoferul, banii
dați la drum, sumele în lei românești, soldul de la începutul lunii — nu apar
deloc. Ele sunt un registru de casă, nu un calcul de rentabilitate, iar
inventarea lor aici ar însemna inventarea unor cifre.

## Ce nu se schimbă retroactiv

O rută salvată este o consemnare a ceea ce s-a cotat, nu un calcul refăcut la
fiecare afișare. Toate cifrele de care are nevoie se copiază pe rândul ei în
momentul salvării — curs, consum, preț combustibil, uzură, vamă, rovinietă,
tarifele de salariu.

Prin urmare **modificarea valorilor implicite, retunarea unui vehicul sau chiar
ștergerea lui nu schimbă nimic la rutele deja salvate.** În lista de rute,
săgeata din dreptul fiecărei curse deschide exact cifrele cu care a fost
calculată, ca lucrul acesta să fie vizibil, nu doar promis.

`tests/Feature/SavedRoutesAreFrozenTest.php` păzește garanția: schimbă *fiecare*
parametru și verifică *fiecare* cifră derivată.

Singura excepție este **simbolul monedei**, care rămâne global — o rută care și-ar
purta propria monedă ar transforma totalurile din Sumar într-o sumă de valute
amestecate. Schimbarea lui reetichetează sumele vechi fără să le convertească, iar
ecranul de Valori implicite o spune.

## Formular necompletat

Calculatorul păzește ce ai introdus. Dacă pleci de pe pagină cu câmpuri
completate și ruta nesalvată, un dialog întreabă întâi: **salvează și continuă**,
**pleacă fără salvare**, sau **rămâi pe pagină**. Salvarea navigează doar dacă a
reușit — o validare picată te lasă pe pagină, la erori.

Reîncărcarea paginii, Back-ul browserului și închiderea tabului primesc
confirmarea nativă a browserului. Aceea nu poate fi stilizată și nu poate aștepta
o salvare — e o regulă de browser, nu o scurtătură.

## Instalare

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
php artisan serve
```

## Vehiculul e obligatoriu

O rută nu poate fi salvată fără un vehicul ales. Coloana rămâne totuși nullable:
ștergerea unui camion lasă rutele lui pe loc, cu cifrele intacte, în loc să le ia
cu el.

## Ce nu e încă făcut

- **Autentificare.** Panoul e deschis: nu există login, roluri sau middleware pe
  rute. Topbar-ul afișează utilizatorul din clipa în care există unul.
- **Distanțe.** Kilometrii se introduc manual; nu e conectat niciun serviciu de
  rutare.
- **Un singur curs valutar.** Prețul se cotează doar în euro. O a doua monedă de
  cotare ar însemna o coloană în plus pe rută.
