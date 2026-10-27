<?php
// Inhalte aller Prä- & Postnatal-Einheiten (Vivien, 24.10.2026): eine Quelle für Teleprompter, Workbook,
// Mitgliederbereich und Verwaltung. Sprechtexte in schönem Hochdeutsch, in Viviens Ton, Du-Form.
// Pro Einheit: title, theme (eine Zeile), checkin (Frage zum Ankommen), impuls (Sprechtext),
// merk (3 Merksätze fürs Workbook), ex (Übungen: name, dose, how = Anleitung/Ansage, tip = Hinweis),
// breath / med ([Name, Sprechtext]), home (für zu Hause), journal (Fragen), close (Abschlusssatz).
declare(strict_types=1);

const NATAL_UNIT_KEYS = ['prae1', 'prae2', 'prae3', 'prae4', 'prae5', 'prae6', 'post1', 'post2', 'post3', 'post4', 'post5', 'post6', 'post7', 'post8'];

function natal_units(): array
{
    static $u = null;
    if ($u !== null) return $u;
    $u = [
    'prae1' => [
        'title' => 'Ankommen im Körper',
        'theme' => 'Wie dein Körper sich verändert und warum Atem, Bauch und Beckenboden ein Team sind.',
        'checkin' => 'Wie fühlt sich dein Körper gerade an, und wie schläfst du im Moment?',
        'impuls' => "Bevor wir uns bewegen, möchte ich dir kurz erzählen, was gerade in deinem Körper passiert. Dein Körper bildet das Hormon Relaxin. Es macht Bänder und Bindegewebe weicher, damit dein Becken sich zur Geburt öffnen kann. Das ist wunderbar, bedeutet aber auch, dass deine Gelenke etwas weniger Halt haben. Wir arbeiten deshalb immer langsam, kontrolliert und nie bis in die letzte Dehnung.\n\nMit dem wachsenden Bauch wandert dein Schwerpunkt nach vorn. Viele Frauen rutschen dann ins Hohlkreuz und spüren es im unteren Rücken. Gleichzeitig weiten sich deine Rippen, damit dein Baby Platz hat und du trotzdem gut atmen kannst.\n\nUnd jetzt das Schönste: Dein Zwerchfell, deine tiefen Bauchmuskeln und dein Beckenboden arbeiten zusammen wie ein Team. Wenn du einatmest, sinkt das Zwerchfell nach unten und der Beckenboden gibt ein wenig nach. Wenn du ausatmest, heben sich beide wieder. Dieses Zusammenspiel wird uns durch alle Einheiten begleiten, vor der Geburt und danach.",
        'merk' => ['Relaxin macht Bänder weicher: langsam bewegen, nie bis ans Ende dehnen.', 'Der Schwerpunkt wandert nach vorn, eine gute Aufrichtung entlastet den Rücken.', 'Zwerchfell, tiefer Bauch und Beckenboden arbeiten mit deinem Atem zusammen.'],
        'ex' => [
            ['Katze-Kuh (Marjaryasana-Bitilasana)', '8–10 ruhige Runden', 'Komm in den Vierfüßlerstand, die Hände unter den Schultern, die Knie unter den Hüften. Mit dem Einatmen lässt du das Brustbein sanft nach vorn wandern und den Blick ein wenig heben. Mit dem Ausatmen rundest du den Rücken, als würdest du dein Baby mit dem Rücken umarmen. Bewege dich so langsam, dass dein Atem die Bewegung führt.', 'Bauch nicht durchhängen lassen, die Bewegung bleibt klein.'],
            ['Beckenkreisen im Sitzen', 'je Richtung 10 Kreise', 'Setz dich aufrecht auf einen stabilen Hocker oder an die Bettkante, die Füße stehen etwas weiter als hüftbreit. Lass dein Becken langsam kreisen, als würdest du mit dem Steißbein einen Kreis auf die Sitzfläche malen. Der Oberkörper bleibt ruhig und aufrecht. Nach zehn Kreisen wechselst du die Richtung.', 'Geht genauso im Stand, mit weichen Knien.'],
            ['Seitliche Dehnung im Sitzen', 'je Seite 4 Atemzüge', 'Bleib aufrecht sitzen und führ den rechten Arm über den Kopf nach links. Atme bewusst in die rechte Flanke, in die Rippen, die sich gerade öffnen. Komm mit dem Ausatmen zurück und wechsle die Seite.', 'Sanft bleiben: Die Rippen weiten sich, der Rücken verdreht sich dabei nicht.'],
            ['Aufrichtung im Stand', '1 Minute', 'Stell dich mit hüftbreiten Füßen hin und verteil dein Gewicht auf den ganzen Fuß. Lass die Knie weich, das Becken ruht ganz neutral. Hebe das Brustbein ein klein wenig und lass die Schultern sinken. Spür, wie dein Bauch getragen wird, statt nach vorn zu ziehen. So kannst du ab heute auch beim Zähneputzen oder in der Warteschlange stehen.', 'Stell dir einen Faden vor, der dich am Scheitel sanft nach oben zieht.'],
        ],
        'breath' => ['360°-Atmung', "Komm in einen bequemen Sitz und leg deine Hände seitlich an die unteren Rippen. Atme durch die Nase ein und lass die Luft in deine Hände strömen, sodass sich die Rippen zur Seite und nach hinten weiten. Mit dem Ausatmen werden die Rippen wieder weich und schmal.\n\nWir nennen das 360°-Atmung, weil sich dein Brustkorb rundherum öffnet, nicht nur nach vorn. Dein Bauch darf dabei weich bleiben. Wir atmen zehnmal so: ein in die Hände, aus und loslassen."],
        'med' => ['Body-Scan im Seitliegen', "Leg dich auf deine linke Seite, ein Kissen zwischen den Knien und eins unter dem Kopf. Deine Hände ruhen an deinem Bauch.\n\nWandere mit deiner Aufmerksamkeit langsam durch deinen Körper. Spür deine Füße, deine Beine und dein Becken, das gerade so viel trägt. Spür deinen Bauch unter deinen Händen und dein Baby darin. Vielleicht bewegt es sich gerade, vielleicht schläft es.\n\nSag ihm in Gedanken einen Satz, ganz egal welchen. Dann atme noch dreimal ruhig ein und aus."],
        'home' => 'Dreimal täglich fünf Atemzüge 360°-Atmung, zum Beispiel morgens im Bett, mittags und abends.',
        'journal' => ['Was fühlt sich in meinem Körper gerade gut an?', 'Wo spüre ich Spannung oder Unsicherheit?', 'Was möchte ich meinem Baby heute sagen?'],
        'close' => 'Du hast heute den Grundstein gelegt. Alles, was wir in den nächsten Einheiten machen, baut auf diesem Atem auf.',
    ],
    'prae2' => [
        'title' => 'Beckenboden: anspannen und loslassen',
        'theme' => 'Wo dein Beckenboden liegt, was er trägt und warum Loslassen genauso wichtig ist wie Kraft.',
        'checkin' => 'Hast du seit dem letzten Mal etwas Neues gespürt, vielleicht Druck nach unten oder Rückenschmerzen?',
        'impuls' => "Heute geht es um deinen Beckenboden. Er spannt sich wie eine Hängematte zwischen Schambein, Steißbein und deinen beiden Sitzbeinhöckern. Er besteht aus mehreren Schichten Muskeln und trägt gerade deine Organe und dein Baby. Er sorgt dafür, dass du dicht bleibst, und er stabilisiert dein Becken.\n\nViele Frauen glauben, ein guter Beckenboden müsse vor allem stark sein. Für die Geburt ist aber etwas anderes genauso wichtig: dass er weich werden und sich öffnen kann. Wir üben deshalb heute beides, sanft anspannen und ganz bewusst loslassen.\n\nWenn du magst, frag deine Hebamme nach der Dammmassage, die du ab etwa der 34. Schwangerschaftswoche machen kannst. Sie kann helfen, das Gewebe auf die Dehnung bei der Geburt vorzubereiten.",
        'merk' => ['Der Beckenboden spannt sich wie eine Hängematte zwischen Schambein, Steißbein und Sitzbeinhöckern.', 'Für die Geburt ist Loslassen genauso wichtig wie Kraft.', 'Ab etwa der 34. SSW: Dammmassage mit der Hebamme besprechen.'],
        'ex' => [
            ['Beckenboden finden im Sitzen', '8 Wiederholungen', 'Setz dich aufrecht auf einen Stuhl und spür deine beiden Sitzbeinhöcker. Atme ein und lass alles weich werden. Mit dem Ausatmen ziehst du die Sitzbeinhöcker in Gedanken sanft zueinander und hebst den Beckenboden ein wenig nach innen und oben, als würdest du eine Heidelbeere aufnehmen. Mit dem nächsten Einatmen lässt du wieder ganz los. Das Loslassen ist genauso wichtig wie das Anspannen.', 'Po, Oberschenkel und Bauch bleiben locker, nur der Beckenboden arbeitet.'],
            ['Clamshell (Pilates) in Seitlage', '2 × 12 je Seite', 'Leg dich auf die Seite, Kopf auf dem Arm, die Knie angewinkelt und die Fersen auf einer Linie mit deinem Po. Mit dem Ausatmen öffnest du das obere Knie, die Füße bleiben zusammen. Mit dem Einatmen schließt du langsam wieder. Das Becken kippt dabei nicht nach hinten.', 'Kräftigt die seitliche Hüfte, die dein Becken stabil hält.'],
            ['Wiegen im Vierfüßlerstand', '1 Minute', 'Komm in den Vierfüßlerstand und wieg dich ganz sanft vor und zurück. Bei jedem Ausatmen spürst du, wie sich dein Beckenboden ein wenig hebt, bei jedem Einatmen, wie er weit wird.', 'Gut bei Druck nach unten, weil das Baby den Beckenboden hier weniger belastet.'],
            ['Unterstützte Yogi-Hocke (Malasana)', '3 × 5 Atemzüge', 'Stell dich vor einen Stuhl oder setz dich auf einen niedrigen Hocker, die Füße weit auseinander und leicht nach außen gedreht. Lass dich tief sinken und halte dich gut fest. Atme in dein Becken und lass mit jedem Einatmen den Beckenboden weich und breit werden. Hier üben wir das Loslassen, das du für die Geburt brauchst.', 'Nicht bei Beckenendlage nach der 35. SSW oder bei Schmerzen am Schambein, dann nur im Sitzen.'],
        ],
        'breath' => ['Beckenboden-Atmung', "Setz dich bequem hin oder bleib im Vierfüßlerstand. Atme durch die Nase ein und lass deinen Bauch, deine Rippen und deinen Beckenboden weit werden, als würde sich eine Blume öffnen. Atme durch den leicht geöffneten Mund aus und spür, wie sich der Beckenboden ganz sanft hebt.\n\nWir machen zehn Atemzüge. Ein und weich werden, aus und sanft heben. Wenn du müde wirst, bleib einfach beim Einatmen und beim Loslassen."],
        'med' => ['Visualisierung „Öffnen“', "Leg dich auf die Seite, ganz bequem mit Kissen. Schließ die Augen, wenn du magst.\n\nStell dir eine Blüte vor, in einer Farbe, die du gern hast. Sie ist noch ganz geschlossen. Mit jedem Ausatmen öffnet sie ein Blütenblatt, ganz langsam, ohne dass du etwas dafür tun musst.\n\nSo darf sich auch dein Körper öffnen, wenn es so weit ist. Nicht mit Kraft, sondern in seinem eigenen Tempo. Bleib noch ein paar Atemzüge bei dieser Blüte."],
        'home' => 'Täglich zweimal zehn Atemzüge mit bewusstem Loslassen beim Einatmen.',
        'journal' => ['Wie leicht fällt mir das Loslassen, im Körper und im Alltag?', 'Wo halte ich gerade fest, obwohl ich es nicht müsste?', 'Was möchte ich meine Hebamme beim nächsten Termin fragen?'],
        'close' => 'Wenn du heute nur eines mitnimmst, dann das Loslassen beim Einatmen. Das wird dir bei der Geburt sehr helfen.',
    ],
    'prae3' => [
        'title' => 'Rücken entlasten, Kraft für den Alltag',
        'theme' => 'Rückenschmerz, Ischias und Symphyse: woher sie kommen und was im Alltag hilft.',
        'checkin' => 'Wie geht es deinem Rücken und deinem Becken, gibt es Bewegungen, die gerade wehtun?',
        'impuls' => "Rückenschmerzen gehören für viele Frauen zur Schwangerschaft dazu, aber du musst sie nicht einfach aushalten. Sie entstehen oft, weil der Bauch nach vorn zieht, die Bänder weicher sind und manche Muskeln mehr arbeiten müssen als vorher. Manchmal zieht es ins Bein, das nennt man Ischias. Manche Frauen spüren auch vorn am Schambein einen Schmerz, besonders beim Treppensteigen oder Umdrehen im Bett. Dann kommt der Schmerz meist von der Symphyse, der Knorpelverbindung vorn zwischen den beiden Beckenhälften.\n\nWas wirklich hilft, ist Kraft. Kräftige Beine und ein starker oberer Rücken nehmen deinem unteren Rücken Arbeit ab. Und ein paar Kleinigkeiten im Alltag bewirken viel: Du stehst über die Seite auf, statt dich gerade hochzuziehen. Du hebst aus den Beinen und atmest dabei aus. Und beim Umdrehen im Bett hältst du die Knie zusammen.\n\nZum Schlafen legst du dich am besten auf die Seite, mit einem Kissen zwischen den Knien und einem kleinen unter dem Bauch.",
        'merk' => ['Kraft in Beinen und oberem Rücken entlastet den unteren Rücken.', 'Über die Seite aufstehen, beim Heben ausatmen, beim Umdrehen die Knie zusammen.', 'Schlafen in Seitlage mit Kissen zwischen den Knien und unter dem Bauch.'],
        'ex' => [
            ['Kniebeuge an der Wand', '2 × 10', 'Stell dich mit dem Rücken an die Wand. Die Füße stehen hüftbreit, ein kleines Stück vor dir. Atme ein und gleite langsam an der Wand nach unten, als würdest du dich auf einen Stuhl setzen. Mit dem Ausatmen drückst du dich über die Fersen wieder nach oben. Die Knie zeigen dabei in Richtung deiner Zehen.', 'Nur so tief, wie es sich gut anfühlt.'],
            ['Rudern mit Band', '2 × 12', 'Setz dich aufrecht hin und leg das Band um deine Füße. Nimm die Enden in die Hände. Mit dem Ausatmen ziehst du die Ellenbogen nach hinten und schiebst die Schulterblätter sanft zueinander. Mit dem Einatmen lässt du langsam wieder nach vorn. Das Brustbein bleibt dabei leicht gehoben.', 'Kräftigt den oberen Rücken, der Bauch und Brust tragen hilft.'],
            ['Bird Dog (Vierfüßlerstand mit Arm und Bein)', '2 × 8 je Seite', 'Komm in den Vierfüßlerstand. Mit dem Ausatmen streckst du den rechten Arm nach vorn und das linke Bein nach hinten, nur so hoch, wie dein Rücken ruhig bleibt. Mit dem Einatmen kommst du zurück. Dann wechselst du die Seite. Stell dir vor, auf deinem Rücken steht eine Tasse Tee, die nicht umfallen darf.', 'Bei Schmerzen am Schambein nur den Arm heben.'],
            ['Seitstütz auf dem Knie', '2 × 20 Sekunden je Seite', 'Leg dich auf die Seite und stütz dich auf den Unterarm, der Ellenbogen unter der Schulter. Die Knie sind angewinkelt. Mit dem Ausatmen hebst du die Hüfte, sodass Knie, Hüfte und Kopf eine Linie bilden. Atme ruhig weiter und halte. Dann sanft absetzen.', 'Bei Schmerzen am Schambein weglassen und stattdessen Clamshells machen.'],
            ['Hüftbeuger dehnen im Halbkniestand', 'je Seite 4 Atemzüge', 'Komm in den Halbkniestand, das rechte Knie auf einer weichen Unterlage, der linke Fuß vorn. Halte dich an einem Stuhl fest. Schieb das Becken sanft nach vorn, bis du vorn an der rechten Hüfte eine Dehnung spürst. Atme ruhig und wechsle dann die Seite.', 'Gut gegen das Hohlkreuz durch den wachsenden Bauch.'],
        ],
        'breath' => ['Kraft-Atmung', "Bei jeder Kraftbewegung atmest du aus und hältst nie die Luft an. Das schützt deinen Beckenboden und deine Bauchmitte.\n\nWir üben das jetzt einmal bewusst im Sitzen. Atme ein und lass alles weit werden. Atme aus und stell dir vor, du hebst gleich etwas Schweres, zum Beispiel eine volle Einkaufstasche. Dein Beckenboden hebt sich, dein Bauch wird fest, und erst dann kommt die Bewegung. Zehnmal: ein, aus und heben."],
        'med' => ['Progressive Muskelentspannung', "Leg dich auf die Seite, ganz bequem mit Kissen.\n\nWir spannen jetzt nacheinander einzelne Körperteile ganz leicht an und lassen sie dann bewusst los. Beginn mit deinen Füßen: Zieh die Zehen ein wenig an, halte kurz und lass los. Dann deine Hände: mach eine sanfte Faust und lass wieder los. Dann deine Schultern: zieh sie ein wenig zu den Ohren und lass sie schwer werden. Zum Schluss dein Gesicht: kneif die Augen kurz zusammen und lass alles weich werden.\n\nSpür nach, wie sich dein Körper jetzt anfühlt. Schwerer, wärmer, ruhiger."],
        'home' => 'Kniebeuge und Rudern, je zweimal zehn Wiederholungen, an drei Tagen pro Woche.',
        'journal' => ['Welche Bewegung im Alltag tut mir gerade nicht gut?', 'Was kann ich ab heute anders machen, beim Heben, Aufstehen, Schlafen?', 'Wo in meinem Körper fühle ich mich stark?'],
        'close' => 'Stärke ist kein Gegensatz zu Weichheit. Ein kräftiger Körper kann sich viel leichter entspannen.',
    ],
    'prae4' => [
        'title' => 'Der Atem als Werkzeug für die Wehen',
        'theme' => 'Die Phasen der Geburt einfach erklärt und wie dein Atem dich durch jede Welle trägt.',
        'checkin' => 'Wie denkst du gerade an die Geburt, eher mit Vorfreude oder mit Sorge?',
        'impuls' => "Heute schauen wir uns die Geburt an, ganz einfach erklärt. In der Eröffnungsphase öffnet sich der Muttermund, Welle für Welle. Das ist meist die längste Phase. Danach kommt oft eine kurze, intensive Übergangsphase, in der viele Frauen das Gefühl haben, es nicht mehr zu schaffen. Das ist ein gutes Zeichen, denn dann ist es fast so weit. In der Austreibungsphase hilft dein Körper dann aktiv mit, dein Baby auf die Welt zu bringen.\n\nVielleicht kennst du den Kreislauf aus Angst, Spannung und Schmerz. Wenn wir Angst haben, spannt sich der Körper an, und Spannung macht den Schmerz stärker. Dein Atem ist das Werkzeug, mit dem du diesen Kreislauf unterbrechen kannst. Eine lange, ruhige Ausatmung sagt deinem Nervensystem: Du bist in Sicherheit. Dann kann dein Körper das Hormon Oxytocin ausschütten, das die Wehen wirksam macht.\n\nDu musst bei der Geburt nichts perfekt machen. Aber ein Atem, den du vorher oft geübt hast, ist dann einfach da.",
        'merk' => ['Eröffnung, Übergang, Austreibung: Die Übergangsphase ist ein Zeichen, dass es fast geschafft ist.', 'Angst bringt Spannung, Spannung verstärkt Schmerz.', 'Eine lange Ausatmung beruhigt das Nervensystem und unterstützt die Wehen.'],
        'ex' => [
            ['Wiegen im Vierfüßlerstand', '2 Minuten', 'Komm in den Vierfüßlerstand und wieg dich vor und zurück, dann von Seite zu Seite, ganz so, wie es sich gut anfühlt. Atme lange aus. Diese Position entlastet deinen Rücken bei den Wehen.', 'Unterarme auf einen Stuhl oder einen Kissenstapel legen, wenn die Handgelenke müde werden.'],
            ['Kniestand mit Anlehnen', '2 Minuten', 'Knie dich vor das Sofa oder das Bett und leg deinen Oberkörper auf die Sitzfläche oder einen Kissenstapel. Lass dein Becken langsam kreisen und atme in deinen Rücken. Spür, wie dein Bauch frei hängen darf.', 'Eine der angenehmsten Positionen in der Eröffnungsphase.'],
            ['Yogi-Hocke (Malasana) am Stuhl', '3 × 5 Atemzüge', 'Halte dich an einem Stuhl oder Tisch fest und sinke in eine tiefe, breite Hocke. Lass dein Becken schwer werden und atme lange aus.', 'Öffnet das Becken. Nicht bei Beckenendlage nach der 35. SSW.'],
            ['An die Wand lehnen und Becken kreisen', '2 Minuten', 'Stell dich mit den Unterarmen an eine Wand, die Füße hüftbreit. Lass dein Becken langsam kreisen oder in Achten schwingen und lass jede Ausatmung lang werden, gern mit einem Ton.', 'Aufrechte Positionen nutzen die Schwerkraft für dein Baby.'],
            ['Becken-Achter im Sitzen', '1 Minute je Richtung', 'Setz dich auf einen stabilen Hocker oder an die Bettkante und male mit deinem Becken eine liegende Acht. Die Bewegung kommt aus dem Becken, der Oberkörper bleibt ruhig.', 'Gut für die Beweglichkeit des Beckens.'],
        ],
        'breath' => ['Wellenatmung und Tönen', "Diese Atmung kannst du bei jeder Wehe nutzen. Atme durch die Nase ein und zähl dabei bis vier. Atme durch den Mund aus und zähl bis sechs oder acht. Die Ausatmung ist länger als die Einatmung, das ist das Geheimnis.\n\nStell dir vor, jede Wehe ist eine Welle. Mit dem Einatmen kommt sie, mit der langen Ausatmung lässt du sie vorbeiziehen.\n\nUnd jetzt probieren wir das Tönen. Atme ein, und beim Ausatmen lässt du einen tiefen Ton entstehen, ein „Ooo“ oder ein „Aaah“. Halte deinen Kiefer dabei ganz weich. Ein weicher Kiefer hilft auch dem Beckenboden, weich zu werden."],
        'med' => ['Jede Welle kommt und geht', "Leg dich auf die Seite und schließ die Augen.\n\nStell dir einen Strand vor. Du sitzt im warmen Sand und schaust aufs Meer. Eine Welle kommt, sie wird größer und höher, und dann bricht sie und läuft aus. Danach ist es eine Weile ganz ruhig. Dann kommt die nächste.\n\nKeine Welle bleibt für immer. Jede kommt und jede geht. Und zwischen den Wellen ist Zeit zum Ausruhen. Atme mit den Wellen, ein, wenn sie kommt, und lange aus, wenn sie ausläuft."],
        'home' => 'Täglich fünf Minuten Wellenatmung, gern in einer Wehenposition.',
        'journal' => ['Was macht mir an der Geburt Sorgen?', 'Was gibt mir Kraft und Zuversicht?', 'Wer soll bei der Geburt bei mir sein, und was wünsche ich mir von dieser Person?'],
        'close' => 'Dein Atem ist immer bei dir. Je öfter du ihn übst, desto selbstverständlicher ist er bei der Geburt da.',
    ],
    'prae5' => [
        'title' => 'Geburtspositionen und Loslassen',
        'theme' => 'Aufrechte Positionen, wie deine Begleitperson helfen kann und deine Wünsche für die Geburt.',
        'checkin' => 'Hast du schon über deine Wünsche für die Geburt nachgedacht oder mit jemandem darüber gesprochen?',
        'impuls' => "Viele Frauen kennen Geburt nur aus Filmen, auf dem Rücken liegend. Für viele ist das aber gar nicht die angenehmste Position. Aufrechte Haltungen nutzen die Schwerkraft, geben dem Becken mehr Raum und lassen dich beweglich bleiben. Du darfst bei der Geburt jede Position wählen, die sich für dich richtig anfühlt, und sie jederzeit wechseln.\n\nDeine Begleitperson kann dir dabei viel helfen: mit einer Massage am Kreuzbein, mit festem Gegendruck an den Hüften, mit Wärme im Rücken und vor allem damit, dass sie dich an deine Atmung erinnert, wenn du sie vergisst. Wenn du magst, üben wir das heute gemeinsam.\n\nSchreib dir auch deine Wünsche für die Geburt auf: welche Positionen du ausprobieren möchtest, ob du Musik magst, wie viel Licht, wer was übernehmen soll. Nicht als festen Plan, sondern damit alle wissen, was dir guttut.",
        'merk' => ['Aufrechte Positionen nutzen die Schwerkraft und geben dem Becken Raum.', 'Die Begleitperson hilft mit Kreuzbeinmassage, Gegendruck und Erinnern an die Atmung.', 'Geburtswünsche aufschreiben, als Orientierung, nicht als starren Plan.'],
        'ex' => [
            ['Positions-Flow', '2 Durchgänge', 'Wir wandern langsam durch die Positionen. Beginne im Stand, an die Wand gelehnt, und kreise das Becken. Komm in den Kniestand und leg deinen Oberkörper auf dem Sofa oder einem Kissenstapel ab. Geh weiter in den Vierfüßlerstand und wiege dich. Zum Schluss legst du dich in die Seitlage, mit einem dicken Kissen zwischen den Knien. Bleib in jeder Position ein paar Atemzüge und spür, welche dir am besten gefällt.', 'Die Lieblingsposition ins Workbook schreiben.'],
            ['Kreuzbeinmassage für die Begleitperson', '1–2 Minuten', 'Komm in den Vierfüßlerstand oder in den Kniestand mit Anlehnen. Deine Begleitperson legt ihren Handballen flach auf dein Kreuzbein, das ist die flache Stelle über dem Po, und massiert in ruhigen Kreisen. Bei einer Wehe darf sie fest und gleichmäßig dagegen drücken. Sag ihr, was sich gut anfühlt.', 'Allein üben: mit einem Tennisball an der Wand.'],
            ['Gegendruck an den Hüften', '3 × 1 Atemzug', 'Deine Begleitperson steht hinter dir und legt beide Handflächen seitlich auf deine Hüften, auf die runden Muskeln am Po. Während du lange ausatmest, drückt sie beide Seiten sanft zueinander. Viele Frauen empfinden das in der Eröffnungsphase als große Erleichterung.', 'Nur so fest, wie es sich gut anfühlt.'],
            ['Kiefer und Hände lösen', '1 Minute', 'Setz dich bequem hin. Spann kurz die Hände zu Fäusten an und lass sie dann ganz weich in den Schoß fallen. Öffne den Mund ein wenig, lass den Unterkiefer locker hängen und atme lang durch den Mund aus. Ein weicher Mund und weiche Hände helfen deinem Beckenboden beim Loslassen.', 'Bei der Geburt: Hände und Gesicht immer wieder bewusst lösen.'],
        ],
        'breath' => ['Atmen nach unten', "Diese Atmung ist für die letzte Phase der Geburt gedacht. Atme durch die Nase ein, ganz ruhig. Atme langsam durch den Mund aus und stell dir vor, dein Atem fließt durch deinen Körper nach unten, in Richtung Becken, als würde er deinem Baby den Weg zeigen.\n\nWir üben das heute ganz sanft. Kein Pressen, kein Luftanhalten, nur ein langer, weicher Atem nach unten. Dein Beckenboden bleibt dabei weit. Zehn Atemzüge."],
        'med' => ['Affirmationen für die Geburt', "Leg dich bequem hin und schließ die Augen. Ich sage dir jetzt ein paar Sätze. Wiederhol sie in Gedanken, wenn sie sich für dich stimmig anfühlen.\n\nMein Körper weiß, was er tut.\nIch öffne mich mit jeder Welle.\nIch vertraue meinem Baby und mir.\nJede Wehe bringt mich meinem Kind näher.\nIch darf um Hilfe bitten.\n\nSuch dir einen Satz aus, der dich besonders berührt hat, und atme noch ein paar Mal mit ihm."],
        'home' => 'Deine Lieblingsaffirmation aufschreiben und sichtbar aufhängen, zum Beispiel am Spiegel.',
        'journal' => ['Welche Position hat sich heute am besten angefühlt?', 'Meine drei wichtigsten Wünsche für die Geburt:', 'Mein Satz für die Geburt lautet:'],
        'close' => 'Du musst bei der Geburt nichts leisten. Du darfst dich führen lassen, von deinem Körper und deinem Baby.',
    ],
    'prae6' => [
        'title' => 'Gut ins Wochenbett',
        'theme' => 'Das Wochenbett realistisch vorbereiten, Hilfe organisieren und auf dich achten.',
        'checkin' => 'Wie fühlst du dich so kurz vor der Geburt, körperlich und mit deinen Gedanken?',
        'impuls' => "Heute ist unsere letzte Einheit vor der Geburt, und wir schauen schon ein Stück weiter: ins Wochenbett. Das sind die ersten sechs bis acht Wochen nach der Geburt. Dein Körper heilt in dieser Zeit eine große Wunde in der Gebärmutter, dein Beckenboden und deine Bauchmitte erholen sich, und deine Hormone stellen sich komplett um.\n\nEine schöne Faustregel ist: fünf Tage im Bett, fünf Tage am Bett, fünf Tage rund ums Bett. Du darfst ruhen, dein Baby kennenlernen und dich versorgen lassen. Organisier dir jetzt schon Hilfe: wer kocht, wer einkauft, wer die Geschwister übernimmt.\n\nUnd noch etwas ist mir wichtig: Viele Frauen weinen in den ersten Tagen nach der Geburt viel und sind sehr dünnhäutig. Das ist der Babyblues, er ist häufig und vergeht meist nach ein paar Tagen. Wenn die Traurigkeit aber länger als zwei Wochen bleibt, wenn du dich leer fühlst oder dich nicht über dein Baby freuen kannst, dann ist das keine Schwäche. Sprich dann bitte mit deiner Hebamme oder deiner Ärztin. Eine Wochenbettdepression ist gut behandelbar.",
        'merk' => ['Wochenbett = 6–8 Wochen Heilung: fünf Tage im Bett, fünf am Bett, fünf rund ums Bett.', 'Hilfe vorher organisieren: Essen, Einkauf, Geschwister, Haushalt.', 'Babyblues vergeht nach wenigen Tagen. Länger als zwei Wochen traurig: Hebamme oder Ärztin ansprechen.'],
        'ex' => [
            ['Aufstehen über die Seite', '3 Mal üben', 'Leg dich auf den Rücken und stell die Beine auf. Roll dich als Ganzes auf die Seite, die Knie bleiben zusammen. Drück dich mit beiden Händen hoch in den Sitz, während die Beine über die Bettkante gleiten. Atme dabei aus. So schonst du deine Bauchmitte nach der Geburt, ganz besonders nach einem Kaiserschnitt.', 'Nach der Geburt jeden Tag so aufstehen.'],
            ['Deine Lieblingssequenz', '10 Minuten', 'Wir machen jetzt noch einmal deine Lieblingsübungen aus unseren Einheiten, ganz ruhig: Katze und Kuh, Beckenkreisen im Sitzen, Kniebeuge an der Wand und Rudern mit Band. Diese Abfolge kannst du bis zur Geburt jeden Tag machen.', 'Abfolge im Workbook festhalten.'],
            ['Seitlage zum Ausruhen', '3 Minuten', 'Leg dich auf die linke Seite, ein Kissen zwischen den Knien, eins unter dem Bauch und eins im Rücken. Lass deinen ganzen Körper schwer werden. Das ist auch eine gute Stillposition für die ersten Tage.', 'Ruhen ist im Wochenbett Training.'],
        ],
        'breath' => ['Notfall-Atmung', "Diese Atmung ist für alle Momente, in denen es dir zu viel wird, bei der Geburt und auch danach, wenn dein Baby weint und du müde bist.\n\nAtme durch die Nase ein und zähl bis vier. Atme durch den Mund aus und zähl bis sechs. Leg dabei eine Hand auf dein Herz. Schon drei solche Atemzüge reichen, um dein Nervensystem zu beruhigen. Wir machen jetzt zehn."],
        'med' => ['Yoga-Nidra-Reise', "Leg dich ganz bequem auf die Seite. Du musst jetzt nichts tun. Du musst nicht einmal wach bleiben.\n\nIch nenne dir nacheinander Stellen deines Körpers, und du wanderst einfach mit deiner Aufmerksamkeit dorthin. Dein rechter Daumen, dein Zeigefinger, dein Mittelfinger. Deine rechte Hand, dein Arm, deine Schulter. Dein linker Daumen, deine linke Hand, dein Arm, deine Schulter. Dein Gesicht, deine Stirn, deine Augen. Dein Bauch und dein Baby darin. Dein Becken, deine Beine, deine Füße.\n\nDein ganzer Körper. Er hat in den letzten Monaten so viel geleistet. Bleib noch einen Moment in dieser Ruhe."],
        'home' => 'Eine Wochenbett-Liste schreiben: Wer hilft wobei, und was brauche ich für mich? Und schreib mir, wenn dein Baby da ist.',
        'journal' => ['Wer hilft mir im Wochenbett wobei?', 'Was brauche ich, damit es mir gut geht?', 'Woran merke ich, dass ich Hilfe brauche, und wen rufe ich dann an?'],
        'close' => 'Ich freue mich so darauf, von dir zu hören, wenn dein Baby da ist. Schreib mir einfach, und wir planen in Ruhe, wann wir weitermachen.',
    ],
    'post1' => [
        'title' => 'Ankommen nach der Geburt',
        'theme' => 'Raum für deine Geburtsgeschichte, und was jetzt in deinem Körper heilt.',
        'checkin' => 'Wie geht es dir und deinem Baby, und wie war die Geburt für dich? Erzähl mir so viel, wie du magst.',
        'impuls' => "Heute geht es vor allem um dich. Jede Geburt ist anders, und manchmal braucht die eigene Geschichte einfach einen Ort, an dem sie erzählt werden darf.\n\nDein Körper heilt gerade an vielen Stellen gleichzeitig. Die Gebärmutter bildet sich zurück, dein Beckenboden hat sich weit gedehnt und findet langsam wieder zurück, und auch deine Bauchmitte braucht Zeit, um sich zu schließen. Dazu kommen die Hormone und der Schlafmangel. Es ist völlig normal, dass sich dein Körper gerade fremd anfühlt.\n\nWir starten deshalb ganz sanft. Ich frage dich noch ein paar Dinge, damit ich dich gut begleiten kann: Verlierst du manchmal Urin, wenn du hustest, lachst oder niest? Spürst du Druck nach unten, als ob etwas herausrutschen möchte? Wie geht es deiner Narbe, falls du eine hast? Und hast du irgendwo Schmerzen? Alles, was du mir erzählst, hilft mir, die Einheiten für dich anzupassen.",
        'merk' => ['Gebärmutter, Beckenboden und Bauchmitte heilen gleichzeitig, das braucht Zeit.', 'Urinverlust, Druckgefühl oder Schmerzen immer ansprechen, das lässt sich gut behandeln.', 'Sanft starten: Atmung und Verbindung kommen vor Kraft.'],
        'ex' => [
            ['Becken kippen in Rückenlage', '10 langsame Runden', 'Leg dich auf den Rücken und stell die Beine hüftbreit auf. Mit dem Ausatmen kippst du dein Becken ganz sanft nach hinten, sodass dein unterer Rücken näher zur Matte kommt. Mit dem Einatmen kehrst du zurück. Die Bewegung ist klein und ruhig.', 'Nach einem Kaiserschnitt nur, solange die Narbe sich gut anfühlt.'],
            ['Verbundene Atmung', '10 Atemzüge', 'Bleib in Rückenlage, eine Hand auf dem Unterbauch. Atme ein und lass den Bauch weich werden. Mit dem Ausatmen ziehst du den Beckenboden sanft nach innen und oben und spürst, wie dein Unterbauch ein wenig mitkommt, ganz leicht, wie ein Flüstern.', 'Grundlage für alle weiteren Übungen.'],
            ['Diastase-Selbsttest', 'einmal, Ergebnis notieren', 'Bleib auf dem Rücken liegen, die Beine aufgestellt. Leg die Fingerspitzen quer auf deine Bauchmitte, knapp über dem Nabel. Heb mit dem Ausatmen den Kopf ganz leicht an. Spür, wie viele Finger zwischen die beiden geraden Bauchmuskeln passen und wie fest oder weich es sich in der Tiefe anfühlt. Wiederhol das auf Höhe des Nabels und knapp darunter.', 'Breite und Spannung im Workbook notieren, wir vergleichen später.'],
            ['Schulterkreisen in Seitlage', 'je Seite 6', 'Leg dich auf die Seite und mach mit dem oberen Arm große, langsame Kreise, als würdest du einen Kreis an die Decke malen. Folge der Hand mit dem Blick. Das öffnet Brust und Schultern, die vom Stillen und Tragen müde sind.', 'Wohltuend bei Nackenverspannungen.'],
        ],
        'breath' => ['Atmung mit Beckenboden-Verbindung', "Leg eine Hand auf dein Herz und eine auf deinen Unterbauch. Atme durch die Nase ein, lass den Bauch weich werden und die Rippen weit. Atme durch den Mund aus und stell dir vor, du ziehst einen Reißverschluss von deinem Beckenboden bis zum Nabel ganz sanft zu.\n\nNicht fest, nur ein leichtes Zusammenziehen. Dann lässt du mit dem Einatmen wieder los. Zehn Atemzüge."],
        'med' => ['Ich bin angekommen', "Bleib liegen und schließ die Augen. Spür den Boden unter dir, der dich trägt.\n\nDein Körper hat ein Kind auf die Welt gebracht. Was immer bei der Geburt passiert ist, du bist hier und dein Baby ist hier.\n\nSag dir in Gedanken: Ich bin angekommen. Ich darf mir Zeit lassen. Mein Körper hat so viel geleistet, und ich danke ihm.\n\nBleib noch einen Moment in diesem Gefühl."],
        'home' => 'Täglich zweimal fünf verbundene Atemzüge, gern beim Stillen.',
        'journal' => ['Wie erinnere ich mich an die Geburt?', 'Was hat mich überrascht, an mir und an meinem Körper?', 'Wofür bin ich meinem Körper dankbar?'],
        'close' => 'Danke, dass du deine Geschichte mit mir geteilt hast. Ab jetzt bauen wir Schritt für Schritt wieder Kraft auf, in deinem Tempo.',
    ],
    'post2' => [
        'title' => 'Den Beckenboden neu spüren',
        'theme' => 'Wie sich dein Beckenboden erholt und wie du ihn im Alltag schützt.',
        'checkin' => 'Wie geht es dir mit dem Beckenboden, spürst du ihn schon wieder gut?',
        'impuls' => "Bei der Geburt hat sich dein Beckenboden um ein Vielfaches gedehnt. Danach braucht er Zeit und gezieltes Training, um wieder kräftig zu werden. Das gilt übrigens auch nach einem Kaiserschnitt, denn auch die Schwangerschaft selbst hat ihn neun Monate lang belastet.\n\nIm Alltag kannst du ihn mit ein paar einfachen Gewohnheiten schützen. Du stehst über die Seite auf. Bevor du hustest oder niest, spannst du ihn kurz an, das nennt man den Knack. Und wenn du etwas hebst, dein Baby, die Babyschale oder den Wäschekorb, atmest du aus und hebst erst dann.\n\nWenn du nach einigen Wochen noch Urin verlierst, Druck nach unten spürst oder Schmerzen beim Sex hast, dann lass dich bitte von einer Beckenboden-Physiotherapeutin untersuchen. Das ist keine Kleinigkeit, und es lässt sich sehr gut behandeln.",
        'merk' => ['Der Beckenboden braucht nach jeder Geburt Zeit und Training, auch nach einem Kaiserschnitt.', 'Der Knack: vor Husten, Niesen und Heben den Beckenboden anspannen.', 'Urinverlust, Druckgefühl oder Schmerzen nach einigen Wochen: Beckenboden-Physiotherapie.'],
        'ex' => [
            ['Schnelle Aktivierungen', '10 Wiederholungen', 'Setz dich aufrecht hin. Spann deinen Beckenboden schnell und kräftig an, eine Sekunde lang, und lass dann für zwei Sekunden ganz los. Diese schnellen Fasern brauchst du, wenn du hustest, niest oder lachst.', 'Das Loslassen ist genauso wichtig wie das Anspannen.'],
            ['Lange Aktivierungen', '5 × 5–10 Sekunden', 'Spann jetzt deinen Beckenboden langsam an, nach innen und oben, und halte die Spannung, während du ruhig weiteratmest. Zähl bis fünf, wenn es geht bis zehn. Dann lass ganz langsam los und ruh dich genauso lange aus.', 'Nicht die Luft anhalten, ruhig weiteratmen.'],
            ['Schulterbrücke (Glute Bridge)', '2 × 10', 'Leg dich auf den Rücken, die Füße hüftbreit aufgestellt. Mit dem Ausatmen hebst du zuerst den Beckenboden, dann rollst du Wirbel für Wirbel das Becken nach oben. Mit dem Einatmen rollst du wieder ab und lässt den Beckenboden los.', 'Kräftigt Po, Beckenboden und hintere Beinmuskeln.'],
            ['Clamshell (Pilates)', '2 × 12 je Seite', 'Leg dich auf die Seite, die Knie angewinkelt. Mit dem Ausatmen hebst du den Beckenboden und öffnest das obere Knie, die Füße bleiben zusammen. Mit dem Einatmen schließt du langsam.', 'Das Becken bleibt dabei ruhig.'],
        ],
        'breath' => ['Der Knack', "Wir üben jetzt die wichtigste Gewohnheit für deinen Alltag. Atme ein. Atme aus, spann deinen Beckenboden an und huste dann kurz. Spür, wie der Beckenboden dich hält.\n\nJetzt das Gleiche mit Heben: Atme ein, atme aus, spann an und heb dabei einen Gegenstand neben dir hoch, zum Beispiel ein Kissen. Zuerst die Spannung, dann die Bewegung. Fünf Mal üben, bis es sich natürlich anfühlt."],
        'med' => ['Mitgefühl mit dem eigenen Körper', "Leg dich hin und leg eine Hand auf deinen Unterbauch.\n\nVielleicht bist du gerade ungeduldig mit deinem Körper, vielleicht fühlt er sich fremd an. Das ist in Ordnung. Stell dir vor, deine beste Freundin hätte gerade ein Baby bekommen. Wie würdest du mit ihr sprechen? Genau so freundlich darfst du jetzt mit dir selbst sprechen.\n\nSag dir in Gedanken: Ich gebe dir Zeit. Ich bin geduldig mit dir. Du machst das gut."],
        'home' => 'Dreimal täglich zehn kurze und fünf lange Aktivierungen, zum Beispiel beim Stillen oder an der Ampel.',
        'journal' => ['Wann vergesse ich, auf meinen Beckenboden zu achten?', 'Mit welcher Alltagssituation verbinde ich meine Übungen?', 'Wie spreche ich gerade mit mir selbst?'],
        'close' => 'Dein Beckenboden ist ein Muskel wie jeder andere. Mit ein paar Minuten am Tag wird er wieder kräftig.',
    ],
    'post3' => [
        'title' => 'Tiefe Bauchmuskeln und Rektusdiastase',
        'theme' => 'Was eine Rektusdiastase ist und wie du deine Bauchmitte sanft wieder schließt.',
        'checkin' => 'Wie fühlt sich dein Bauch gerade an, hast du den Selbsttest noch einmal gemacht?',
        'impuls' => "In der Schwangerschaft weichen die beiden geraden Bauchmuskeln auseinander, um Platz für dein Baby zu machen. Das nennt man Rektusdiastase, und sie ist am Ende der Schwangerschaft bei fast allen Frauen da. Bei vielen schließt sie sich nach der Geburt von selbst, bei manchen braucht sie Training.\n\nWichtiger als die Breite in Fingern ist die Spannung in der Tiefe. Wenn sich die Bauchmitte fest anfühlt, kann sie Kraft übertragen, auch wenn noch zwei Finger dazwischen passen. Unser Ziel ist deshalb nicht nur, den Spalt zu schließen, sondern die Bauchmitte wieder belastbar zu machen.\n\nAchte bei allen Übungen auf ein Zeichen: Wenn sich deine Bauchmitte wie ein kleines Zelt oder ein Kamm nach oben wölbt, das nennt man Coning, dann ist die Übung im Moment noch zu schwer. Mach sie dann kleiner. Klassische Sit-ups und Crunches lassen wir vorerst weg.",
        'merk' => ['Eine Rektusdiastase ist nach der Schwangerschaft normal.', 'Die Spannung in der Tiefe ist wichtiger als die Breite in Fingern.', 'Wölbt sich der Bauch zeltartig (Coning): Übung kleiner machen. Vorerst keine Crunches.'],
        'ex' => [
            ['Transversus-Aktivierung (querer Bauchmuskel)', '10 Wiederholungen', 'Leg dich auf den Rücken, die Beine aufgestellt. Atme ein und lass den Bauch weich werden. Atme aus und zieh den Bauchnabel sanft Richtung Wirbelsäule, als würdest du einen Gürtel ein Loch enger schnallen. Der Beckenboden hebt sich mit. Halte kurz und lass wieder los.', 'Der Rücken bleibt ruhig auf der Matte.'],
            ['Heel Slides', '2 × 10 je Seite', 'Bleib in Rückenlage. Mit dem Ausatmen aktivierst du die Bauchmitte und schiebst eine Ferse langsam über den Boden, bis das Bein fast gestreckt ist. Mit dem Einatmen ziehst du es wieder heran. Dann die andere Seite.', 'Mit Socken auf glattem Boden geht es leichter.'],
            ['Toe Taps', '2 × 10 je Seite', 'Heb in Rückenlage beide Beine nacheinander in die Tischposition, die Knie über den Hüften. Mit dem Ausatmen senkst du einen Fuß und tippst mit den Zehen auf den Boden. Mit dem Einatmen kommst du zurück. Der untere Rücken bleibt ruhig.', 'Zu schwer? Ein Bein bleibt aufgestellt.'],
            ['Dead Bug (vereinfacht)', '2 × 6 je Seite', 'Bleib in der Tischposition und streck die Arme zur Decke. Mit dem Ausatmen bewegst du den rechten Arm nach hinten und das linke Bein nach vorn, nur so weit, wie dein Bauch flach bleibt. Mit dem Einatmen zurück, dann die andere Seite.', 'Achte auf Coning. Zeigt es sich, verkleinerst du den Bewegungsweg.'],
            ['Vierfüßler mit Atem', '8 Atemzüge', 'Komm in den Vierfüßlerstand. Atme ein und lass den Bauch Richtung Boden hängen. Atme aus und zieh ihn sanft nach innen und oben, ohne den Rücken zu runden.', 'Die Schwerkraft macht die Übung hier etwas anspruchsvoller.'],
        ],
        'breath' => ['Reißverschluss-Atmung', "Leg dich auf den Rücken, die Hände auf deinem Bauch. Atme ein und lass den Bauch weit werden. Atme langsam aus und stell dir einen Reißverschluss vor, der vom Schambein bis zum Brustbein langsam zugezogen wird. Zuerst hebt sich der Beckenboden, dann wird der Unterbauch flach, dann schließt sich die Bauchmitte bis zu den Rippen.\n\nMit dem Einatmen öffnest du den Reißverschluss wieder. Zehn Atemzüge."],
        'med' => ['Entspannung in Rückenlage', "Leg ein Kissen unter deine Knie und lass deine Beine ganz schwer werden. Deine Arme liegen locker neben dir, die Handflächen zeigen nach oben.\n\nSpür deinen Bauch, der sich mit jedem Atemzug hebt und senkt. Er hat dein Baby getragen und darf jetzt in Ruhe heilen. Mit jedem Ausatmen sinkt dein Körper ein wenig tiefer in den Boden. Bleib so liegen, solange du magst."],
        'home' => 'Heel Slides und Toe Taps, je zweimal zehn Wiederholungen, an vier Tagen pro Woche.',
        'journal' => ['Mein Selbsttest heute: wie viele Finger, wie fest?', 'Bei welchen Bewegungen im Alltag spüre ich meine Bauchmitte?', 'Wie fühle ich mich in meinem Bauch gerade?'],
        'close' => 'Deine Bauchmitte braucht Geduld, nicht Härte. Jede ruhige Wiederholung bringt dich weiter.',
    ],
    'post4' => [
        'title' => 'Haltung, Tragen und Stillen',
        'theme' => 'Nacken und Schultern entlasten und dein Baby rückenfreundlich heben und tragen.',
        'checkin' => 'Wo spürst du das Tragen und Stillen gerade am meisten, im Nacken, in den Schultern oder im Rücken?',
        'impuls' => "Du trägst dein Baby, du stillst oder gibst das Fläschchen, du beugst dich über den Wickeltisch und hebst die Babyschale. Kein Wunder, dass Nacken und Schultern verspannen und der obere Rücken rund wird.\n\nBeim Stillen hilft es, das Baby zu dir zu bringen, nicht dich zum Baby. Ein Stillkissen, ein Kissen unter dem Arm und eine Fußstütze helfen dabei sehr. Die Schultern sinken, der Rücken lehnt sich an.\n\nBeim Heben gehst du in die Knie, hältst das Baby oder die Babyschale nah am Körper und atmest aus, während du hochkommst. Den Kinderwagen schiebst du aufrecht, mit dem Griff etwa auf Hüfthöhe.\n\nHeute kräftigen wir deinen oberen Rücken und öffnen deine Brust, damit dein Körper diese Arbeit leichter tragen kann.",
        'merk' => ['Beim Stillen das Baby zu dir bringen, nicht dich zum Baby. Kissen unter den Arm, Rücken anlehnen.', 'Aus den Knien heben, Baby nah am Körper, dabei ausatmen.', 'Kinderwagen-Griff auf Hüfthöhe, aufrecht schieben.'],
        'ex' => [
            ['Open Book (Rotation der Brustwirbelsäule in Seitlage)', '6 je Seite', 'Leg dich auf die Seite, die Knie angewinkelt und übereinander, die Arme lang vor dir. Mit dem Einatmen öffnest du den oberen Arm wie ein Buch zur anderen Seite und folgst ihm mit dem Blick. Die Knie bleiben zusammen. Mit dem Ausatmen schließt du das Buch wieder.', 'Öffnet die Brustwirbelsäule, die vom Stillen rund wird.'],
            ['Rudern mit Band', '2 × 12', 'Setz dich aufrecht hin, das Band um die Füße. Mit dem Ausatmen ziehst du die Ellenbogen nach hinten und schiebst die Schulterblätter zueinander und nach unten, weg von den Ohren. Mit dem Einatmen lässt du langsam nach vorn.', 'Ohne Band: im Stehen nach vorn gebeugt mit zwei Wasserflaschen.'],
            ['Brustdehnung im Türrahmen', '4 Atemzüge je Seite', 'Stell dich in einen Türrahmen und leg den Unterarm auf Schulterhöhe an den Rahmen. Mach einen kleinen Schritt nach vorn, bis du vorn in der Brust eine Dehnung spürst. Atme ruhig und wechsle die Seite.', 'Ideal direkt nach dem Stillen.'],
            ['Kniebeuge mit Baby oder Gewicht', '2 × 10', 'Stell dich hüftbreit hin und halte dein Baby oder ein Gewicht nah vor der Brust. Mit dem Einatmen setzt du dich nach hinten unten, mit dem Ausatmen hebst du den Beckenboden und kommst über die Fersen wieder hoch.', 'Genau so hebst du im Alltag.'],
            ['Wall Angels an der Wand', '8 Wiederholungen', 'Stell dich mit dem Rücken an die Wand, die Füße ein kleines Stück davor. Leg Arme und Handrücken in U-Form an die Wand. Schieb die Arme mit dem Einatmen langsam nach oben und mit dem Ausatmen wieder nach unten, die Schultern bleiben weg von den Ohren.', 'Nur so weit, wie die Arme an der Wand bleiben können.'],
        ],
        'breath' => ['Schultern loslassen', "Setz dich bequem hin. Atme ein und zieh die Schultern bis zu den Ohren hoch. Halte kurz. Atme hörbar durch den Mund aus und lass die Schultern schwer nach unten fallen.\n\nWir machen das fünfmal. Danach atmest du noch fünfmal ruhig weiter und spürst, wie viel Platz jetzt zwischen deinen Schultern und deinen Ohren ist."],
        'med' => ['Die 5-Minuten-Pause', "Diese kleine Pause kannst du überall machen, auf dem Sofa, beim Stillen oder wenn dein Baby schläft.\n\nSchließ die Augen oder senk den Blick. Spür zuerst deine Füße auf dem Boden. Dann deinen Atem, ohne ihn zu verändern. Dann deine Schultern, und lass sie ein Stück sinken. Dann dein Gesicht, deine Stirn, deinen Kiefer, und lass alles weich werden.\n\nSag dir in Gedanken: Ich darf jetzt kurz bei mir sein. Bleib einige Atemzüge, dann öffne langsam die Augen."],
        'home' => 'Nach dem Stillen eine Brustdehnung im Türrahmen und zehn Schulterkreise.',
        'journal' => ['Welche Haltung nehme ich beim Stillen oder Füttern meistens ein?', 'Was kann ich an meinem Still- oder Tragplatz verändern?', 'Wann gönne ich mir heute meine 5-Minuten-Pause?'],
        'close' => 'Du trägst so viel, im wahrsten Sinn. Ein kräftiger oberer Rücken macht das alles ein bisschen leichter.',
    ],
    'post5' => [
        'title' => 'Kraft für den Alltag mit Baby',
        'theme' => 'Belastung langsam steigern und die Warnzeichen deines Körpers kennen.',
        'checkin' => 'Wie fühlst du dich in deinem Körper, schon wieder kräftiger als noch vor ein paar Wochen?',
        'impuls' => "Heute machen wir einen Schritt weiter und bauen echte Kraft auf. Dein Alltag mit Baby ist körperlich anstrengend, und dein Körper soll dafür gewappnet sein.\n\nWichtig ist, die Belastung langsam zu steigern und auf deinen Körper zu hören. Es gibt ein paar Warnzeichen, bei denen eine Übung im Moment noch zu viel ist: wenn du Druck nach unten spürst, wenn du Urin verlierst, wenn sich die Bauchmitte zeltartig wölbt oder wenn etwas schmerzt. Dann gehst du einen Schritt zurück, machst die Übung leichter oder reduzierst die Wiederholungen.\n\nDas ist kein Rückschritt. Das ist kluges Training, und genau so wirst du langfristig stark.",
        'merk' => ['Belastung langsam steigern: lieber eine Wiederholung zu wenig als zu viel.', 'Warnzeichen: Druck nach unten, Urinverlust, Coning, Schmerz.', 'Bei einem Warnzeichen die Übung leichter machen, das ist kluges Training.'],
        'ex' => [
            ['Kniebeugen', '3 × 10', 'Stell dich hüftbreit hin, die Arme vor dir. Mit dem Einatmen setzt du dich nach hinten unten, als würdest du dich auf einen Stuhl setzen. Mit dem Ausatmen hebst du den Beckenboden und drückst dich über die Fersen hoch.', 'Steigern: mit Baby auf dem Arm oder Gewicht.'],
            ['Ausfallschritt rückwärts', '2 × 8 je Seite', 'Halte dich an einer Stuhllehne fest. Mach mit dem rechten Bein einen großen Schritt nach hinten und senk das Knie Richtung Boden. Mit dem Ausatmen kommst du über die vordere Ferse zurück. Dann die andere Seite.', 'Das vordere Knie bleibt über dem Fuß.'],
            ['Einbeinige Schulterbrücke (Single-Leg Glute Bridge)', '2 × 8 je Seite', 'Leg dich auf den Rücken, die Füße aufgestellt. Streck ein Bein lang aus. Mit dem Ausatmen hebst du den Beckenboden und drückst dich über die aufgestellte Ferse nach oben. Das Becken bleibt gerade. Mit dem Einatmen senkst du ab.', 'Zu schwer? Mit beiden Beinen beginnen.'],
            ['Seitstütz auf dem Knie', '2 × 20 Sekunden je Seite', 'Leg dich auf die Seite, stütz dich auf den Unterarm, die Knie angewinkelt. Mit dem Ausatmen hebst du die Hüfte, bis Knie, Hüfte und Kopf eine Linie bilden. Atme ruhig weiter und halte.', 'Steigern: Beine gestreckt.'],
            ['Rudern mit Band', '2 × 12', 'Befestige das Band an einer Türklinke oder leg es um die Füße. Mit dem Ausatmen ziehst du die Ellenbogen nach hinten und die Schulterblätter zueinander. Mit dem Einatmen lässt du langsam nach vorn.', 'Schultern bleiben weg von den Ohren.'],
        ],
        'breath' => ['Atem in der Anstrengung', "Bei allen Kraftübungen gilt: Ausatmen in der Anstrengung, einatmen in der Entlastung. Wir üben das jetzt im Stehen mit einer Kniebeuge.\n\nAtme ein und setz dich nach hinten unten. Atme aus, heb den Beckenboden und komm hoch. Spür, wie sich dein ganzer Körper in diesem Moment verbindet. Fünf Wiederholungen, ganz bewusst mit dem Atem."],
        'med' => ['Ich bin stark und getragen', "Leg dich hin und lass deinen Körper nach dem Training schwer werden.\n\nSpür, wie dein Herz noch etwas schneller schlägt und langsam ruhiger wird. Spür die Wärme in deinen Muskeln. Dein Körper ist stark. Er hat ein Kind getragen und geboren, und er trägt es jetzt jeden Tag.\n\nUnd gleichzeitig darfst du dich tragen lassen, vom Boden unter dir, von den Menschen um dich herum. Sag dir: Ich bin stark, und ich bin getragen."],
        'home' => 'Ein 10-Minuten-Kraftprogramm aus Kniebeugen, Brücke und Rudern, dreimal pro Woche.',
        'journal' => ['Was kann mein Körper heute, was vor ein paar Wochen noch nicht ging?', 'Habe ich ein Warnzeichen bemerkt, und wobei?', 'Wann passen meine 10 Minuten am besten in meinen Tag?'],
        'close' => 'Du bist schon so viel stärker geworden. Bleib dran, mit Geduld und mit Freude.',
    ],
    'post6' => [
        'title' => 'Nervensystem, Schlafmangel und Mental Load',
        'theme' => 'Was Schlafmangel und Hormone mit dir machen und wie du dich in Minuten regulierst.',
        'checkin' => 'Wie schläfst du gerade, und wie geht es dir mit allem, was du im Kopf behalten musst?',
        'impuls' => "Heute machen wir etwas ganz Ruhiges, denn heute geht es um dein Nervensystem. Schlafmangel, die Umstellung der Hormone und die ständige Verantwortung für ein kleines Wesen bringen viele Mütter an ihre Grenzen. Du bist nicht schwach, wenn du dich erschöpft fühlst. Das ist einfach menschlich.\n\nDazu kommt der Mental Load: an alles denken, alles planen, alles im Blick behalten. Diese Arbeit ist unsichtbar, aber sie kostet viel Kraft. Ein erster Schritt ist, sie sichtbar zu machen. Schreib einmal auf, woran du alles denkst, und geh die Liste mit deinem Partner oder deiner Partnerin durch.\n\nUnd weil es mir wichtig ist, noch einmal: Wenn du dich über längere Zeit leer, hoffnungslos oder wie abgeschnitten fühlst, sprich bitte mit deiner Hebamme oder deiner Ärztin. Eine Wochenbettdepression kann auch noch Monate nach der Geburt beginnen, und sie ist gut behandelbar.\n\nSelbstfürsorge muss übrigens nicht lange dauern. Schon drei Minuten können dein Nervensystem beruhigen.",
        'merk' => ['Erschöpfung nach der Geburt ist menschlich, keine Schwäche.', 'Mental Load sichtbar machen: aufschreiben und teilen.', 'Länger leer oder hoffnungslos: Hebamme oder Ärztin ansprechen, auch Monate nach der Geburt.'],
        'ex' => [
            ['Schütteln', '1 Minute', 'Stell dich hin und fang an, deine Hände zu schütteln, dann die Arme, die Schultern, die Beine, bis dein ganzer Körper locker schüttelt. Lass dabei ruhig einen Ton heraus. Dann bleib ganz still stehen und spür nach.', 'Baut Anspannung über den Körper ab.'],
            ['Wiegen', '2 Minuten', 'Stell dich hüftbreit hin und verlagere dein Gewicht sanft von einem Bein aufs andere, wie du es mit deinem Baby im Arm machst. Lass deine Arme locker mitschwingen. Dieses Wiegen beruhigt auch dich.', 'Gern mit geschlossenen Augen.'],
            ['Nacken und Schultern mobilisieren', '1 Minute', 'Setz dich bequem hin. Lass den Kopf langsam zur rechten Schulter sinken, atme, und dann zur linken. Roll die Schultern langsam nach hinten. Alles ganz sanft, ohne Druck.', 'Kleine Bewegungen, viel Atem.'],
            ['Kindhaltung', '5 Atemzüge', 'Komm in den Vierfüßlerstand und setz dich mit dem Po Richtung Fersen, die Knie etwas auseinander. Leg die Stirn auf die Hände oder ein Kissen. Atme in deinen Rücken.', 'Kissen zwischen Po und Fersen, wenn die Knie ziehen.'],
            ['Beine an die Wand', '3 Minuten', 'Leg dich mit dem Po nah an eine Wand und leg die Beine an der Wand nach oben. Die Arme liegen locker neben dir. Lass dich vom Boden tragen.', 'Wohltuend bei schweren Beinen.'],
        ],
        'breath' => ['Verlängerte Ausatmung und Summen', "Setz dich bequem hin oder bleib liegen. Atme durch die Nase ein, zähl bis vier. Atme durch die Nase aus und summe dabei leise, wie eine Biene, solange es angenehm ist.\n\nSpür, wie das Summen in deinem Kopf und deiner Brust vibriert. Diese Vibration beruhigt dein Nervensystem besonders schnell. Wir summen zehnmal. Das kannst du übrigens auch mit deinem Baby im Arm machen, viele Babys lieben es."],
        'med' => ['Yoga Nidra', "Leg dich ganz bequem hin, deck dich zu, wenn du magst. Für die nächsten Minuten musst du gar nichts. Wenn du einschläfst, ist das in Ordnung.\n\nNimm dir zuerst einen kleinen Satz für dich, einen Wunsch, zum Beispiel: Ich bin gut so, wie ich bin. Sag ihn dir dreimal in Gedanken.\n\nJetzt wandere ich mit dir durch deinen Körper. Deine rechte Hand, jeder einzelne Finger, dein Handgelenk, dein Unterarm, dein Ellenbogen, deine Schulter. Deine linke Hand, jeder Finger, dein Arm, deine Schulter. Dein rechter Fuß, deine Zehen, deine Ferse, dein Bein. Dein linker Fuß, deine Zehen, dein Bein. Dein Becken, dein Bauch, dein Rücken. Deine Brust, dein Hals, dein Gesicht.\n\nDein ganzer Körper liegt jetzt hier, schwer und ruhig.\n\nStell dir einen Ort vor, an dem du dich ganz sicher fühlst. Schau dich dort um, hör, was es dort zu hören gibt, spür die Luft auf deiner Haut.\n\nKomm am Ende noch einmal zu deinem Satz zurück und sag ihn dir dreimal. Dann beweg langsam Finger und Zehen und komm in deinem Tempo zurück."],
        'home' => 'Jeden Tag drei Minuten Mini-Pause mit Summen.',
        'journal' => ['Woran denke ich alles, jeden Tag? (Mental-Load-Liste)', 'Was davon kann jemand anderes übernehmen?', 'Was gibt mir in drei Minuten Kraft zurück?'],
        'close' => 'Du darfst dir Pausen nehmen, ohne sie dir zu verdienen. Sie gehören einfach dazu.',
    ],
    'post7' => [
        'title' => 'Zurück zur Lieblingsbewegung',
        'theme' => 'Wann Laufen, Springen und Sport wieder passen und wie du sicher zurückkommst.',
        'checkin' => 'Welche Bewegung vermisst du am meisten, und worauf freust du dich?',
        'impuls' => "Viele Frauen fragen mich, wann sie wieder laufen, springen oder zu ihrem Sport zurückkehren dürfen. Die ehrliche Antwort ist: Es kommt weniger auf die Wochen an als darauf, was dein Körper schon kann.\n\nAls Orientierung gilt, dass Laufen und Springen meist frühestens ab etwa zwölf Wochen nach der Geburt sinnvoll sind, und nur, wenn du keine Beschwerden hast. Also kein Urinverlust, kein Druck nach unten, keine Schmerzen.\n\nHeute machen wir deshalb einen Belastungs-Check. Das ist kein Test, den du bestehen musst. Er zeigt uns einfach, wo du gerade stehst und was der nächste Schritt für dich ist. Wenn eine Übung noch nicht ohne Beschwerden geht, ist das wertvolle Information, keine Niederlage.",
        'merk' => ['Laufen und Springen meist frühestens ab ca. 12 Wochen, nur ohne Beschwerden.', 'Entscheidend ist, was dein Körper schon kann, nicht die Wochenzahl.', 'Beschwerden beim Belastungs-Check: einen Schritt zurück und weiter aufbauen.'],
        'ex' => [
            ['Einbeinstand', '30 Sekunden je Seite', 'Stell dich auf ein Bein, das andere Knie leicht gehoben. Halte dein Gleichgewicht, atme ruhig und spür deinen Beckenboden.', 'Gelingt das sicher, ist das ein gutes Zeichen.'],
            ['Einbeinige Kniebeuge am Stuhl', '10 je Seite', 'Stell dich auf ein Bein vor einen Stuhl und setz dich langsam hin, dann komm auf einem Bein wieder hoch. Das Knie bleibt über dem Fuß.', 'Halte dich an etwas fest, wenn du unsicher bist.'],
            ['Wadenheben einbeinig', '15–20 je Seite', 'Stell dich auf ein Bein, halte dich leicht an der Wand fest und heb die Ferse so hoch wie möglich. Dann senk sie langsam wieder ab.', 'Kräftige Waden sind beim Laufen wichtig.'],
            ['Zügiges Gehen auf der Stelle', '1 Minute', 'Marschier zügig auf der Stelle und schwing die Arme mit. Achte darauf, ob du Druck nach unten spürst.', 'Ohne Beschwerden: weiter zum Hüpfen.'],
            ['Leichtes Hüpfen', '10 Mal, nur ohne Beschwerden', 'Hüpf ganz leicht auf beiden Beinen auf der Stelle, die Knie weich. Spannst du vorher den Beckenboden an und bleibst dicht, ist dein Körper bereit für den nächsten Schritt.', 'Bei Urinverlust oder Druck sofort aufhören und ins Workbook notieren.'],
            ['Ganzkörper-Flow', '5 Minuten', 'Zum Schluss verbinden wir alles: Kniebeuge, Ausfallschritt, Vierfüßler mit Arm und Bein und Seitstütz, ganz im Rhythmus deines Atems.', 'Die Abfolge steht im Workbook.'],
        ],
        'breath' => ['Atem im Rhythmus', "Wenn du dich wieder mehr bewegst, darf dein Atem einen Rhythmus finden. Geh langsam auf der Stelle und atme zwei Schritte lang ein und drei Schritte lang aus. Die Ausatmung ist etwas länger.\n\nWenn das leicht ist, versuch drei Schritte ein und vier aus. Spür, wie dein Atem deine Bewegung trägt."],
        'med' => ['Deine Lieblingsbewegung', "Leg dich hin und schließ die Augen.\n\nStell dir vor, es ist ein paar Monate später. Du machst deine Lieblingsbewegung, vielleicht läufst du durch den Wald, vielleicht tanzt du, vielleicht stehst du auf einem Berggipfel. Dein Körper fühlt sich stark und frei an.\n\nWie fühlt sich das an? Was hörst du, was siehst du? Nimm dieses Bild mit, als kleine Vorfreude und als Ziel."],
        'home' => 'Zweimal pro Woche 20 Minuten zügig mit dem Kinderwagen gehen.',
        'journal' => ['Mein Belastungs-Check heute: was ging gut, was noch nicht?', 'Meine Lieblingsbewegung, auf die ich mich freue:', 'Mein nächster kleiner Schritt dorthin:'],
        'close' => 'Du bist auf einem richtig guten Weg zurück zu deiner Lieblingsbewegung.',
    ],
    'post8' => [
        'title' => 'Dein Programm für zu Hause',
        'theme' => 'Rückblick auf deinen Weg und dein persönliches 15-Minuten-Programm.',
        'checkin' => 'Wenn du an unsere erste Einheit zurückdenkst, was hat sich seitdem verändert?',
        'impuls' => "Heute ist unsere letzte gemeinsame Einheit, und ich möchte mit dir zurückschauen. Du hast in den letzten Wochen so viel für dich getan, neben allem, was ein Leben mit Baby verlangt.\n\nWorauf bist du stolz? Was kannst du heute, was am Anfang noch nicht ging? Und was nimmst du für dich mit?\n\nDamit es weitergeht, stellen wir heute dein ganz persönliches Programm für zu Hause zusammen. Fünfzehn Minuten, die du dreimal pro Woche machen kannst, mit den Übungen, die dir am meisten gutgetan haben. Du findest es danach auch in deinem Workbook.",
        'merk' => ['Rückblick: Was kann ich heute, was am Anfang noch nicht ging?', 'Dein 15-Minuten-Programm dreimal pro Woche.', 'Deine Lieblingsatmung als Ritual für den Alltag.'],
        'ex' => [
            ['Ankommen mit dem Atem', '1 Minute', 'Beginne jedes Training mit fünf Atemzügen mit Beckenboden-Verbindung: einatmen und weich werden, ausatmen und sanft heben.', ''],
            ['Katze und Kuh', '8 Runden', 'Im Vierfüßlerstand den Rücken mit dem Atem runden und lang machen.', ''],
            ['Heel Slides oder Dead Bug', '2 × 8 je Seite', 'Je nachdem, was dir gerade gut gelingt. Achte auf eine flache Bauchmitte.', ''],
            ['Schulterbrücke (Glute Bridge)', '2 × 10', 'Mit dem Ausatmen den Beckenboden heben und das Becken hochrollen.', ''],
            ['Kniebeuge', '2 × 10', 'Mit Baby oder Gewicht, ausatmen beim Hochkommen.', ''],
            ['Rudern mit Band', '2 × 12', 'Schulterblätter zueinander und nach unten.', ''],
            ['Seitstütz', '2 × 20 Sekunden je Seite', 'Auf den Knien oder mit gestreckten Beinen.', ''],
            ['Brustdehnung', '4 Atemzüge je Seite', 'Im Türrahmen, zum Abschluss.', ''],
        ],
        'breath' => ['Deine Lieblingsatmung', "Welche Atmung hat dir in unseren Einheiten am meisten gutgetan? Die 360°-Atmung, die Wellenatmung, das Summen oder der Reißverschluss?\n\nWir machen sie jetzt noch einmal gemeinsam, zehn Atemzüge lang. Und dann such dir einen festen Moment in deinem Tag, an dem du sie ab jetzt machst, zum Beispiel morgens vor dem Aufstehen oder abends, wenn dein Baby eingeschlafen ist."],
        'med' => ['Abschlussritual', "Leg dich hin und leg beide Hände auf deinen Bauch.\n\nDenk an die Frau, die du warst, als wir uns kennengelernt haben. Denk an alles, was seitdem passiert ist. An die Geburt, an die ersten Nächte, an jeden kleinen Schritt zurück in deinen Körper.\n\nSag dir in Gedanken: Ich bin zurück bei mir. Ich bin stark, ich bin weich, und ich bin gut so, wie ich bin.\n\nAtme noch dreimal tief ein und aus. Danke, dass ich dich auf diesem Weg begleiten durfte."],
        'home' => 'Dein 15-Minuten-Programm, dreimal pro Woche.',
        'journal' => ['Darauf bin ich stolz:', 'Das nehme ich für mich mit:', 'Mein fester Moment für meine Lieblingsatmung:'],
        'close' => 'Es war mir eine große Freude, dich zu begleiten. Wenn du magst, freue ich mich sehr über eine kurze Bewertung bei Google, damit auch andere Frauen den Weg zu mir finden.',
    ],
    ];
    return $u;
}

/** Titel einer Einheit inkl. Phase, z. B. „Pränatal 3 · Rücken entlasten …“ */
function natal_unit_label(string $key): string
{
    $u = natal_units()[$key] ?? null;
    if (!$u) return $key === 'kennenlernen' ? 'Kennenlerngespräch' : 'Eigenes Skript';
    return (str_starts_with($key, 'prae') ? 'Pränatal ' : 'Postnatal ') . substr($key, -1) . ' · ' . $u['title'];
}

/** Fertiges Teleprompter-Skript einer Einheit (Platzhalter {name}, Hinweise in [eckigen Klammern]). */
function natal_unit_script(string $key): string
{
    $u = natal_units()[$key] ?? null;
    if (!$u) return '';
    $s = natal_unit_label($key) . "\n\n";
    $s .= "[0–5 Min · Ankommen]\nHallo {name}, schön, dass du da bist. " . $u['checkin'] . "\n[zuhören, Beschwerden und Fragen seit dem letzten Mal notieren]\nKomm jetzt ganz in Ruhe auf deiner Matte an. Wir atmen dreimal gemeinsam: durch die Nase ein, und lang durch den Mund aus.\n\n";
    $s .= "[5–15 Min · Impuls]\n" . $u['impuls'] . "\n\n";
    $s .= "[15–35 Min · Bewegung]\nUnd jetzt kommen wir in Bewegung. Wenn sich etwas nicht gut anfühlt, sag es mir sofort, dann passen wir es an.\n\n";
    foreach ($u['ex'] as $i => [$n, $d, $how, $tip]) {
        $s .= '[Übung ' . ($i + 1) . ' · ' . $n . ' · ' . $d . "]\n" . $how . "\n" . ($tip !== '' ? '[' . $tip . "]\n" : '') . "\n";
    }
    $s .= '[35–45 Min · Breathwork · ' . $u['breath'][0] . "]\n" . $u['breath'][1] . "\n\n";
    $s .= '[45–55 Min · Meditation · ' . $u['med'][0] . "]\n" . $u['med'][1] . "\n\n";
    $s .= "[55–60 Min · Abschluss]\nKomm langsam wieder in den Sitz. Wie hat es sich heute für dich angefühlt?\n" . $u['close'] . "\nFür zu Hause: " . $u['home'] . "\nDein Workbook mit allen Übungen von heute findest du gleich auf deiner Seite.\n[nächsten Termin ausmachen]";
    return $s;
}

/** Kennenlerngespräch: 30 Minuten, vor allem zuhören. */
function natal_call_script(): string
{
    return "Kennenlerngespräch · 30 Minuten\n\n[0–3 Min · Begrüßung]\nHallo {name}, schön, dass du dir Zeit nimmst. Ich bin Vivien. Ich begleite Frauen in der Schwangerschaft und nach der Geburt, sanft, kräftigend und ganz auf sie abgestimmt. Heute geht es nur darum, dass wir uns kennenlernen und du alle Fragen stellen kannst. Du musst dich heute zu nichts entscheiden.\n\n[3–15 Min · Zuhören]\nErzähl mir ein bisschen von dir. Wie geht es dir gerade?\nIn welcher Woche bist du, oder wie alt ist dein Baby?\nWie war deine Schwangerschaft bisher, oder, wenn dein Baby schon da ist, wie war die Geburt für dich?\nBewegst du dich im Moment, und was hast du früher gern gemacht?\nGibt es Beschwerden, auf die ich achten soll, zum Beispiel Rücken, Becken, Beckenboden oder die Narbe?\nHast du das Training schon mit deiner Hebamme oder deiner Gynäkologin besprochen?\n[Notizen machen, Warnzeichen beachten: Blutungen, vorzeitige Wehen, Bluthochdruck → erst ärztliche Freigabe]\n\n[15–22 Min · So arbeite ich]\nJede Einheit dauert 60 Minuten und hat immer denselben Ablauf: Wir kommen an, ich gebe dir einen kurzen Impuls zu einem Thema, dann bewegen wir uns, danach kommen Breathwork und eine Meditation. Zum Schluss bekommst du eine kleine Übung für zu Hause und nach jeder Einheit ein Workbook mit allem, was wir gemacht haben.\nWir können uns im Studio in Mathon treffen oder live per Video, ganz wie es für dich passt.\n\n[22–27 Min · Pakete]\nPränatal sind es sechs Einheiten bis zur Geburt, postnatal acht Einheiten für die Zeit danach. Am schönsten ist die Kombi, dann begleite ich dich durch beides. Alle Pakete gelten bis sechs Monate nach der Geburt, und wenn dein Baby früher kommt, nutzt du die offenen Einheiten einfach danach.\n[Preise je nach Variante nennen, Studio oder online]\n\n[27–30 Min · Abschluss]\nHast du noch Fragen?\nWenn du magst, bekommst du gleich nach unserem Gespräch eine E-Mail mit einem kleinen Kennenlern-Bonus für die nächsten Tage. Ich würde mich sehr freuen, dich zu begleiten, {name}.";
}

// --- Affirmationen für den Mitgliederbereich (wechseln morgens / tagsüber / abends, prä / post) ---------
const NATAL_AFFIRM = [
    'prae' => [
        'morgen' => ['Mein Körper weiß, was er tut, und ich vertraue ihm.', 'Heute gehe ich sanft mit mir um.', 'Ich atme ein und mache Platz für mein Baby.', 'Ich bin genau richtig, so wie ich heute bin.', 'Mein Atem trägt mich durch diesen Tag.', 'Ich darf langsam sein.', 'Mit jedem Tag wachsen meine Kraft und meine Vorfreude.'],
        'tag' => ['Ich mache eine kleine Pause und spüre mein Baby.', 'Ich darf um Hilfe bitten.', 'Ich höre auf meinen Körper, er spricht mit mir.', 'Mein Becken öffnet sich, mein Herz auch.', 'Ich bin stark und weich zugleich.'],
        'abend' => ['Ich lasse den Tag los, Atemzug für Atemzug.', 'Mein Baby und ich sind geborgen.', 'Ich habe heute genug getan.', 'Ich vertraue dem Weg, der vor uns liegt.', 'Mein Körper darf jetzt ruhen.', 'Jede Welle wird kommen und gehen.'],
    ],
    'post' => [
        'morgen' => ['Auch an müden Tagen bin ich gut so, wie ich bin.', 'Heute bin ich geduldig mit meinem Körper.', 'Mein Körper hat Großes geleistet.', 'Ich darf mir heute etwas Gutes tun.', 'Ich beginne den Tag mit einem tiefen Atemzug für mich.', 'Ich muss nicht perfekt sein, um eine gute Mama zu sein.'],
        'tag' => ['Ich nehme mir drei Minuten nur für mich.', 'Ich darf Hilfe annehmen.', 'Ich bin stark, und ich bin getragen.', 'Mein Beckenboden und ich werden jeden Tag mehr zu einem Team.', 'Ich spreche mit mir so freundlich wie mit meiner besten Freundin.'],
        'abend' => ['Ich lasse los, was heute nicht geschafft wurde.', 'Ich habe heute so viel gegeben.', 'Mein Körper heilt, auch während ich schlafe.', 'Ich bin zurück bei mir, Schritt für Schritt.', 'Für heute ist es gut.', 'Ich bin dankbar für diesen Tag mit meinem Kind.'],
    ],
];

/** Affirmation des Tages: [Tageszeit-Label, Text]. Wechselt mit dem Datum, morgens/tagsüber/abends verschieden. */
function natal_affirmation(string $phase, ?int $ts = null): array
{
    $ts ??= time();
    $h = (int)date('G', $ts);
    $part = $h < 11 ? 'morgen' : ($h < 17 ? 'tag' : 'abend');
    $list = NATAL_AFFIRM[$phase === 'post' ? 'post' : 'prae'][$part];
    $doy = (int)date('z', $ts) + (int)date('Y', $ts) * 7;
    return [['morgen' => 'Für deinen Morgen', 'tag' => 'Für deinen Tag', 'abend' => 'Für deinen Abend'][$part], $list[$doy % count($list)]];
}

// --- Englische Teleprompter-Fassungen (Vivien, 25.10.2026: alle Sprechtexte auf Deutsch und Englisch) ---------
// Daten in api/seed/natal-units-en.json (Titel, Check-in, Impuls, Übungen, Breathwork, Meditation, für zu Hause, Abschluss).

function natal_units_en(): array
{
    static $u = null;
    if ($u === null) $u = json_decode((string)@file_get_contents(__DIR__ . '/seed/natal-units-en.json'), true) ?: [];
    return $u;
}

function natal_unit_label_en(string $key): string
{
    $u = natal_units_en()[$key] ?? null;
    if (!$u) return $key === 'kennenlernen' ? 'Discovery call' : $key;
    return (str_starts_with($key, 'prae') ? 'Prenatal ' : 'Postnatal ') . substr($key, -1) . ' · ' . $u['title'];
}

/** Englisches Teleprompter-Skript einer Einheit, gleicher Ablauf wie natal_unit_script(). */
function natal_unit_script_en(string $key): string
{
    $u = natal_units_en()[$key] ?? null;
    if (!$u) return '';
    $s = natal_unit_label_en($key) . "\n\n";
    $s .= "[0–5 min · Arriving]\nHi {name}, it's so lovely to see you. " . $u['checkin'] . "\n[listen, note any symptoms and questions since last time]\nTake your time and settle onto your mat. Let's take three breaths together: in through the nose, and a long breath out through the mouth.\n\n";
    $s .= "[5–15 min · Talk]\n" . $u['impuls'] . "\n\n";
    $s .= "[15–35 min · Movement]\nNow let's start moving. If anything doesn't feel good, tell me straight away and we'll adapt it.\n\n";
    foreach ($u['ex'] as $i => [$n, $d, $how, $tip]) {
        $s .= '[Exercise ' . ($i + 1) . ' · ' . $n . ' · ' . $d . "]\n" . $how . "\n" . ($tip !== '' ? '[' . $tip . "]\n" : '') . "\n";
    }
    $s .= '[35–45 min · Breathwork · ' . $u['breath'][0] . "]\n" . $u['breath'][1] . "\n\n";
    $s .= '[45–55 min · Meditation · ' . $u['med'][0] . "]\n" . $u['med'][1] . "\n\n";
    $s .= "[55–60 min · Closing]\nCome slowly back up to sitting. How did today feel for you?\n" . $u['close'] . "\nFor home: " . $u['home'] . "\nYou'll find your workbook with all of today's exercises on your page shortly.\n[book the next session]";
    return $s;
}

function natal_call_script_en(): string
{
    return "Discovery call · 30 minutes\n\n[0–3 min · Welcome]\nHi {name}, thank you so much for taking the time. I'm Vivien. I support women through pregnancy and after the birth, in a way that is gentle, strengthening and completely tailored to them. Today is simply about getting to know each other, and you can ask me anything you like. You don't have to decide anything today.\n\n[3–15 min · Listening]\nTell me a little about yourself. How are you at the moment?\nHow many weeks pregnant are you, or how old is your baby?\nHow has your pregnancy been so far, or how was the birth for you?\nAre you exercising at the moment, and what did you love doing before?\nAre there any symptoms I should keep an eye on, for example your back, your pelvis, your pelvic floor or your scar?\nHave you already talked to your midwife or your gynaecologist about exercising?\n[take notes, watch for warning signs: bleeding, preterm contractions, high blood pressure → medical clearance first]\n\n[15–22 min · How I work]\nEvery session lasts 60 minutes and always follows the same flow: we arrive, I give you a short talk on one topic, then we move, followed by breathwork and a meditation. At the end you get a small practice for home, and after every session a workbook with everything we did.\nWe can meet in the studio in Mathon or live by video, whatever suits you best.\n\n[22–27 min · Packages]\nThe prenatal package is six sessions before the birth, and the postnatal package is eight sessions for the time afterwards. The loveliest option is the combination, because then I'm with you through both. All packages are valid until six months after the birth, and if your baby comes early, you simply use the remaining sessions afterwards.\n[name the prices for the chosen option, studio or online]\n\n[27–30 min · Closing]\nDo you have any more questions?\nIf you like, right after our call you'll get an email with a small welcome bonus for the next few days. I would love to work with you, {name}.";
}
