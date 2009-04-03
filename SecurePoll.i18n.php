<?php
/**
 * Internationalisation file for SecurePoll extension.
 *
 * @file
 * @ingroup Extensions
*/

$messages = array();

/** English
 * @author Tim Starling
 */
$messages['en'] = array(
	# Top-level
	'securepoll' => 'SecurePoll',
	'securepoll-desc' => 'Extension for elections and surveys',
	'securepoll-invalid-page' => 'Invalid subpage "<nowiki>$1</nowiki>"',
	
	# Vote (most important to translate)
	'securepoll-too-few-params' => 'Not enough subpage parameters (invalid link).',
	'securepoll-invalid-election' => '"$1" is not a valid election ID.',
	'securepoll-not-authorised' => 'You are not authorised to vote in this election.',
	'securepoll-welcome' => '<strong>Welcome $1!</strong>',
	'securepoll-not-started' => 'This election has not yet started.
It is scheduled to start at $1.',
	'securepoll-not-qualified' => 'You are not qualified to vote in this election: $1',
	'securepoll-change-disallowed' => 'You have voted in this election before.
Sorry, you may not vote again.',
	'securepoll-change-allowed' => '<strong>Note: You have voted in this election before.</strong>
You may change your vote by submitting the form below.
Note that if you do this, your original vote will be discarded.',
	'securepoll-submit' => 'Submit vote',
	'securepoll-gpg-receipt' => 'Thank you for voting.

If you wish, you may retain the following receipt as evidence of your vote:

<pre>$1</pre>',
	'securepoll-thanks' => 'Thank you, your vote has been recorded.',
	'securepoll-return' => 'Return to $1',
	'securepoll-encrypt-error' => 'Failed to encrypt your vote record.
Your vote has not been recorded!

$1',
	'securepoll-no-gpg-home' => 'Unable to create GPG home directory.',
	'securepoll-secret-gpg-error' => 'Error executing GPG.
Use $wgSecurePollShowErrorDetail=true; in LocalSettings.php to show more detail.',
'securepoll-full-gpg-error' => 'Error executing GPG:

Command: $1

Error:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG keys are configured incorrectly.',
	'securepoll-gpg-parse-error' => 'Error interpreting GPG output.',
	'securepoll-no-decryption-key' => 'No decryption key is configured.
Cannot decrypt.',
	
	# List page
	# Mostly for admins
	'securepoll-list-title' => 'List votes: $1',
	'securepoll-header-timestamp' => 'Time',
	'securepoll-header-user-name' => 'Name',
	'securepoll-header-user-domain' => 'Domain',
	'securepoll-header-ip' => 'IP',
	'securepoll-header-xff' => 'XFF',
	'securepoll-header-ua' => 'User agent',
	'securepoll-header-token-match' => 'CSRF',
	'securepoll-header-strike' => 'Strike',
	'securepoll-header-details' => 'Details',
	'securepoll-strike-button' => 'Strike',
	'securepoll-unstrike-button' => 'Unstrike',
	'securepoll-strike-reason' => 'Reason:',
	'securepoll-strike-cancel' => 'Cancel',
	'securepoll-strike-error' => 'Error performing strike/unstrike: $1',
	'securepoll-need-admin' => 'You need to be an admin to perform this action.',
	'securepoll-details-link' => 'Details',

	# Details page
	# Mostly for admins
	'securepoll-details-title' => 'Vote details: #$1',
	'securepoll-invalid-vote' => '"$1" is not a valid vote ID',
	'securepoll-header-id' => 'ID',
	'securepoll-header-user-type' => 'User type',
	'securepoll-header-authority' => 'URL',
	'securepoll-voter-properties' => 'Voter properties',
	'securepoll-strike-log' => 'Strike log',
	'securepoll-header-action' => 'Action',
	'securepoll-header-reason' => 'Reason',
	'securepoll-header-admin' => 'Admin',

	# Dump page
	'securepoll-dump-title' => 'Dump: $1',
	'securepoll-dump-no-crypt' => 'No encrypted election record is available for this election, because the election is not configured to use encryption.',
	'securepoll-dump-not-finished' => 'Encrypted election records are only available after the finish date: $1',
	'securepoll-dump-no-urandom' => 'Cannot open /dev/urandom. 
To maintain voter privacy, encrypted election records are only publically available when they can be shuffled with a secure random number stream.',

	# Translate page
	'securepoll-translate-title' => 'Translate: $1',
	'securepoll-invalid-language' => 'Invalid language code "$1"',
	'securepoll-header-trans-id' => 'ID',
	'securepoll-submit-translate' => 'Update',
	'securepoll-language-label' => 'Select language: ',
	'securepoll-submit-select-lang' => 'Translate',
);

/** Message documentation (Message documentation)
 * @author EugeneZelenko
 * @author Fryed-peach
 * @author IAlex
 * @author Kwj2772
 * @author Purodha
 * @author Raymond
 */
$messages['qqq'] = array(
	'securepoll-desc' => 'A short description of this extension shown in [[Special:Version]].
{{doc-important|Do not translate tag names.}}
{{doc-important|Do not translate links.}}',
	'securepoll-not-started' => '$1 is a data/time, $2 is the date of it, $3 is its time.',
	'securepoll-return' => '{{Identical|Return to $1}}',
	'securepoll-secret-gpg-error' => "<span style=\"color:red\">'''DO <u>NOT</u> translate LocalSettings.php and \$wgSecurePollShowErrorDetail=true;'''</span>",
	'securepoll-header-timestamp' => '{{Identical|Time}}',
	'securepoll-header-user-name' => '{{Identical|Name}}',
	'securepoll-header-ip' => '{{optional}}',
	'securepoll-header-xff' => '{{optional}}',
	'securepoll-header-token-match' => '{{optional}}',
	'securepoll-header-details' => '{{Identical|Details}}',
	'securepoll-strike-button' => '{{Identical|Strike}}',
	'securepoll-unstrike-button' => '{{Identical|Unstrike}}',
	'securepoll-strike-reason' => '{{Identical|Reason}}',
	'securepoll-strike-cancel' => '{{Identical|Cancel}}',
	'securepoll-details-link' => '{{Identical|Details}}',
	'securepoll-header-id' => '{{optional}}',
	'securepoll-header-authority' => '{{optional}}',
	'securepoll-header-reason' => '{{Identical|Reason}}',
	'securepoll-dump-no-urandom' => 'Do not translate "/dev/urandom".',
	'securepoll-header-trans-id' => '{{optional}}',
);

/** Belarusian (Taraškievica orthography) (Беларуская (тарашкевіца))
 * @author EugeneZelenko
 * @author Jim-by
 */
$messages['be-tarask'] = array(
	'securepoll' => 'Бясьпечнае галасаваньне',
	'securepoll-desc' => 'Пашырэньне для выбараў і апытаньняў',
	'securepoll-invalid-page' => 'Няслушная падстаронка «<nowiki>$1</nowiki>»',
	'securepoll-too-few-params' => 'Недастаткова парамэтраў падстаронкі (няслушная спасылка).',
	'securepoll-invalid-election' => '«$1» — няслушны ідэнтыфікатар выбараў.',
	'securepoll-not-authorised' => 'Вам неабходна аўтарызавацца, каб прыняць удзел у гэтых выбарах.',
	'securepoll-welcome' => '<strong>Вітаем, $1!</strong>',
	'securepoll-not-started' => 'Гэтыя выбары яшчэ не пачаліся.
Яны павінны пачацца $1.',
	'securepoll-not-qualified' => 'Вы не адпавядаеце крытэрам удзелу ў гэтых выбарах: $1',
	'securepoll-change-disallowed' => 'Вы ўжо галасавалі ў гэтых выбарах.
Прабачце, Вам нельга галасаваць паўторна.',
	'securepoll-change-allowed' => '<strong>Заўвага: Вы ўжо галасавалі ў гэтых выбарах.</strong>
Вы можаце зьмяніць Ваш голас, запоўніўшы форму ніжэй.
Заўважце, што калі Вы гэта зробіце, Ваш першапачатковы голас будзе ануляваны.',
	'securepoll-submit' => 'Даслаць голас',
	'securepoll-gpg-receipt' => 'Дзякуй за ўдзел ў галасаваньні.

Калі Вы жадаеце, Вы можаце атрымаць наступнае пацьверджаньне Вашага голасу:

<pre>$1</pre>',
	'securepoll-thanks' => 'Дзякуем, Ваш голас быў прыняты.',
	'securepoll-return' => 'Вярнуцца да $1',
	'securepoll-encrypt-error' => 'Памылка шыфраваньня Вашага голасу для запісу.
Ваш голас ня быў прыняты!

$1',
	'securepoll-no-gpg-home' => 'Немагчыма стварыць хатнюю дырэкторыю GPG.',
	'securepoll-secret-gpg-error' => 'Памылка выкананьня GPG.
Устанавіце $wgSecurePollShowErrorDetail=true; у LocalSettings.php каб паглядзець падрабязнасьці.',
	'securepoll-full-gpg-error' => 'Памылка выкананьня GPG:

Каманда: $1

Памылка:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'Ключы GPG былі няслушна сканфігураваны.',
	'securepoll-gpg-parse-error' => 'Памылка інтэрпрэтацыі вынікаў GPG.',
	'securepoll-no-decryption-key' => 'Няма скафігураваных ключоў для расшыфраваньня.
Немагчыма расшыфраваць.',
	'securepoll-list-title' => 'Сьпіс галасоў: $1',
	'securepoll-header-timestamp' => 'Час',
	'securepoll-header-user-name' => 'Імя',
	'securepoll-header-user-domain' => 'Дамэн',
	'securepoll-header-ua' => 'Агент удзельніка',
	'securepoll-header-strike' => 'Закрэсьліваньне',
	'securepoll-header-details' => 'Падрабязнасьці',
	'securepoll-strike-button' => 'Закрэсьліць',
	'securepoll-unstrike-button' => 'Адкрэсьліць',
	'securepoll-strike-reason' => 'Прычына:',
	'securepoll-strike-cancel' => 'Адмяніць',
	'securepoll-strike-error' => 'Памылка пад час закрэсьліваньня/адкрэсьліваньня: $1',
	'securepoll-need-admin' => 'Вам неабходна мець правы адміністратара, каб выканаць гэтае дзеяньне.',
	'securepoll-details-link' => 'Падрабязнасьці',
	'securepoll-details-title' => 'Падрабязнасьці галасаваньня: #$1',
	'securepoll-invalid-vote' => '«$1» не зьяўляецца слушным ідэнтыфікатарам голасу',
	'securepoll-header-user-type' => 'Тып удзельніка',
	'securepoll-voter-properties' => 'Зьвесткі пра выбаршчыка',
	'securepoll-strike-log' => 'Журнал закрэсьліваньняў',
	'securepoll-header-action' => 'Дзеяньне',
	'securepoll-header-reason' => 'Прычына',
	'securepoll-header-admin' => 'Адміністратар',
);

/** Bosnian (Bosanski)
 * @author CERminator
 */
$messages['bs'] = array(
	'securepoll' => 'Sigurno glasanje',
	'securepoll-desc' => 'Proširenje za izbore i ankete',
	'securepoll-invalid-page' => 'Nevaljana podstranica "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Nema dovoljno parametara podstranice (nevaljan link).',
	'securepoll-invalid-election' => '"$1" nije valjan izborni ID.',
	'securepoll-not-authorised' => 'Niste ovlašteni da glasate na ovim izborima.',
	'securepoll-welcome' => '<strong>Dobrodošao $1!</strong>',
	'securepoll-not-started' => 'Ovo glasanje još nije počelo.
Planirani početak glasanja je $1.',
	'securepoll-not-qualified' => 'Niste kvalificirani da učestvujete na ovom glasanju: $1',
	'securepoll-change-disallowed' => 'Već ste ranije glasali na ovom glasanju.
Žao nam je, ne možete više glasati.',
	'securepoll-change-allowed' => '<strong>Napomena: Već ste ranije glasali na ovom glasanju.</strong>
Možete promijeniti Vaš glas slanjem obrasca ispod.
Zapamtite da ako ovo učinite, Vaš prvobitni glas će biti nevažeći.',
	'securepoll-submit' => 'Pošalji glas',
	'securepoll-gpg-receipt' => 'Hvala Vam za glasanje.

Ako želite, možete zadržati slijedeću potvrdu kao dokaz Vašeg glasanja:

<pre>$1</pre>',
	'securepoll-thanks' => 'Hvala Vam, Vaš glas je zapisan.',
	'securepoll-return' => 'Nazad na $1',
	'securepoll-encrypt-error' => 'Šifriranje Vašeg zapisa glasanja nije uspjelo.
Vaš glas nije sačuvan!

$1',
	'securepoll-no-gpg-home' => 'Nemoguće napraviti GPG početni direktorijum.',
	'securepoll-secret-gpg-error' => 'Greška pri izvršavanju GPG.
Koristite $wgSecurePollShowErrorDetail=true; u LocalSettings.php za više detalja.',
	'securepoll-full-gpg-error' => 'Greška pri izvršavanju GPG:

Komanda: $1

Grešaka:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG ključevi nisu pravilno podešeni.',
	'securepoll-gpg-parse-error' => 'Greška pri obradi GPG izlaza.',
	'securepoll-no-decryption-key' => 'Nijedan dekripcijski ključ nije podešen.
Ne može se dekriptovati.',
	'securepoll-list-title' => 'Spisak glasova: $1',
	'securepoll-header-timestamp' => 'Vrijeme',
	'securepoll-header-user-name' => 'Ime',
	'securepoll-header-user-domain' => 'Domena',
	'securepoll-header-ua' => 'Korisnički agent',
	'securepoll-header-strike' => 'Precrtaj',
	'securepoll-header-details' => 'Detalji',
	'securepoll-strike-button' => 'Precrtaj',
	'securepoll-unstrike-button' => 'Poništi precrtavanje',
	'securepoll-strike-reason' => 'Razlog:',
	'securepoll-strike-cancel' => 'Odustani',
	'securepoll-strike-error' => 'Greška izvšavanja precrtavanja/uklanjanja: $1',
	'securepoll-need-admin' => 'Morate biti admin da bi ste izvršili ovu akciju.',
	'securepoll-details-link' => 'Detalji',
	'securepoll-details-title' => 'Detalji glasanja: #$1',
	'securepoll-invalid-vote' => '"$1" nije valjan glasački ID',
	'securepoll-header-user-type' => 'Tip korisnika',
	'securepoll-voter-properties' => 'Svojstva glasača',
	'securepoll-strike-log' => 'Zapisnik precrtavanja',
	'securepoll-header-action' => 'Akcija',
	'securepoll-header-reason' => 'Razlog',
	'securepoll-header-admin' => 'Admin',
);

/** Catalan (Català)
 * @author SMP
 * @author Vriullop
 */
$messages['ca'] = array(
	'securepoll' => 'Vot segur',
	'securepoll-desc' => 'Extensió per a eleccions i enquestes',
	'securepoll-invalid-page' => 'Subpàgina «<nowiki>$1</nowiki>» invàlida',
	'securepoll-too-few-params' => 'No hi ha prou paràmetres de subpàgina (enllaç invàlid).',
	'securepoll-invalid-election' => "«$1» no és un identificador d'elecció vàlid.",
	'securepoll-not-authorised' => 'No esteu autoritzat a votar en aquesta elecció.',
	'securepoll-welcome' => '<strong>Benvingut $1!</strong>',
	'securepoll-not-started' => 'Aquesta elecció encara no ha començat.
Està programada per a que comenci el $1.',
	'securepoll-not-qualified' => 'No esteu qualificat per votar en aquesta elecció: $1',
	'securepoll-change-disallowed' => 'Ja heu votat en aquesta elecció.
Disculpeu, no podeu tornar a votar.',
	'securepoll-change-allowed' => '<strong>Nota: Ja heu votat en aquesta elecció.</strong>
Podeu canviar el vostre vot trametent el següent formulari.
Si ho feu, el vostre vot anterior serà descartat.',
	'securepoll-submit' => 'Tramet el vot',
	'securepoll-gpg-receipt' => 'Gràcies per votar.

Si ho desitgeu, podeu conservar el següent comprovant del vostre vot:

<pre>$1</pre>',
	'securepoll-thanks' => 'Gràcies, el vostre vot ha estat enregistrat.',
	'securepoll-return' => 'Torna a $1',
	'securepoll-encrypt-error' => "No s'ha aconseguit encriptar el registre del vostre vot.
El vostre vot no ha estat enregistrat!

$1",
	'securepoll-gpg-parse-error' => 'Error en la interpretació de la sortida de GPG',
	'securepoll-no-decryption-key' => 'No està configurada la clau de desxifrat.
No es pot desencriptar.',
	'securepoll-header-timestamp' => 'Hora',
	'securepoll-header-user-name' => 'Nom',
	'securepoll-header-user-domain' => 'Domini',
	'securepoll-header-strike' => 'Anuŀlació',
	'securepoll-header-details' => 'Detalls',
	'securepoll-strike-button' => 'Anuŀla',
	'securepoll-unstrike-button' => "Desfés l'anuŀlació",
	'securepoll-strike-reason' => 'Motiu:',
	'securepoll-strike-cancel' => 'Canceŀla',
	'securepoll-need-admin' => 'Heu de ser administrador per a realitzar aquesta acció.',
	'securepoll-details-link' => 'Detalls',
	'securepoll-details-title' => 'Detalls de vot: #$1',
	'securepoll-invalid-vote' => '«$1» no és una ID de vot vàlida',
	'securepoll-header-user-type' => "Tipus d'usuari",
	'securepoll-voter-properties' => 'Propietats del votant',
	'securepoll-strike-log' => "Registre d'anuŀlacions",
	'securepoll-header-action' => 'Acció',
	'securepoll-header-reason' => 'Motiu',
	'securepoll-header-admin' => 'Administrador',
	'securepoll-translate-title' => 'Traducció: $1',
	'securepoll-invalid-language' => "Codi d'idioma «$1» no vàlid",
	'securepoll-submit-translate' => 'Actualitza',
	'securepoll-language-label' => 'Escolliu idioma:',
	'securepoll-submit-select-lang' => 'Tradueix',
);

/** Czech (Česky)
 * @author Mormegil
 */
$messages['cs'] = array(
	'securepoll' => 'Bezpečné hlasování',
	'securepoll-desc' => 'Rozšíření pro hlasování a průzkumy',
	'securepoll-invalid-page' => 'Neplatná podstránka „<nowiki>$1</nowiki>“',
	'securepoll-too-few-params' => 'Nedostatek parametrů pro podstránku (neplatný odkaz).',
	'securepoll-invalid-election' => '„$1“ není platný identifikátor hlasování.',
	'securepoll-not-authorised' => 'V těchto volbách nejste {{GENDER:|oprávněn|oprávněna|oprávněn(a)}} hlasovat.',
	'securepoll-welcome' => '<strong>Vítejte, {{GRAMMAR:$1|uživateli|uživatelko|uživateli}} $1!</strong>',
	'securepoll-not-started' => 'Toto hlasování dosud nebylo zahájeno.
Mělo by začít $1.',
	'securepoll-not-qualified' => 'Nesplňujete podmínky pro účast v tomto hlasování: $1',
	'securepoll-change-disallowed' => 'Tohoto hlasování jste se již {{GRAMMAR:zúčastnil|zúčastnila|zúčastnil}}.
Je mi líto, ale znovu hlasovat nemůžete.',
	'securepoll-change-allowed' => '<strong>Poznámka: Tohoto hlasování jste se již {{GRAMMAR:|zúčastnil|zúčastnila|zúčastnil}}.</strong>
Pokud chcete svůj hlas změnit, odešlete níže uvedený formulář.
Uvědomte si, že pokud to uděláte, váš původní hlas bude zahozen.',
	'securepoll-submit' => 'Odeslat hlas',
	'securepoll-gpg-receipt' => 'Děkujeme za váš hlas.

Pokud chcete, můžete si uschovat následující potvrzení vašeho hlasování:

<pre>$1</pre>',
	'securepoll-thanks' => 'Děkujeme vám, váš hlas byl zaznamenán.',
	'securepoll-return' => 'Vrátit se na stránku $1',
	'securepoll-encrypt-error' => 'Nepodařilo se zašifrovat záznam o vašem hlasování.
Váš hlas nebyl zaznamenán!

$1',
	'securepoll-no-gpg-home' => 'Nepodařilo se vytvořit domácí adresář pro GPG.',
	'securepoll-secret-gpg-error' => 'Chyba při provádění GPG.
Pokud chcete zobrazit podrobnosti, nastavte <code>$wgSecurePollShowErrorDetail=true;</code> v <tt>LocalSettings.php</tt>.',
	'securepoll-full-gpg-error' => 'Chyba při provádění GPG:

Příkaz: $1

Chyba:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'Jsou chybně nakonfigurovány klíče pro GPG.',
	'securepoll-gpg-parse-error' => 'Chyba při zpracovávání výstupu GPG.',
	'securepoll-no-decryption-key' => 'Nebyl nakonfigurován dešifrovací klíč.
Nelze dešifrovat.',
	'securepoll-list-title' => 'Seznam hlasů – $1',
	'securepoll-header-timestamp' => 'Čas',
	'securepoll-header-user-name' => 'Jméno',
	'securepoll-header-user-domain' => 'Doména',
	'securepoll-header-ua' => 'Prohlížeč',
	'securepoll-header-strike' => 'Škrtnuto',
	'securepoll-header-details' => 'Podrobnosti',
	'securepoll-need-admin' => 'K provedení této operace byste {{GENDER:|musel|musela|musel}} být správce.',
	'securepoll-details-link' => 'Podrobnosti',
	'securepoll-details-title' => 'Podrobnosti hlasu #$1',
	'securepoll-invalid-vote' => '„$1“ není platný identifikátor hlasu',
);

/** German (Deutsch)
 * @author ChrisiPK
 * @author Metalhead64
 */
$messages['de'] = array(
	'securepoll' => 'Sichere Abstimmung',
	'securepoll-desc' => 'Erweiterung für Wahlen und Umfragen',
	'securepoll-invalid-page' => 'Ungültige Unterseite „<nowiki>$1</nowiki>“',
	'securepoll-too-few-params' => 'Nicht genügend Unterseitenparameter (ungültiger Link).',
	'securepoll-invalid-election' => '„$1“ ist keine gültige Abstimmungs-ID.',
	'securepoll-not-authorised' => 'Du bist nicht berechtigt, bei dieser Wahl abzustimmen.',
	'securepoll-welcome' => '<strong>Willkommen $1!</strong>',
	'securepoll-not-started' => 'Diese Wahl hat noch nicht begonnen.
Sie beginnt voraussichtlich am $1.',
	'securepoll-not-qualified' => 'Du bist nicht qualifiziert, bei dieser Wahl abzustimmen: $1',
	'securepoll-change-disallowed' => 'Du hast bereits bei dieser Wahl abgestimmt.
Du darfst nicht erneut abstimmen.',
	'securepoll-change-allowed' => '<strong>Hinweis: Du hast bei dieser Wahl bereits abgestimmt.</strong>
Du kannst deine Stimme ändern, indem du das untere Formular abschickst.
Wenn du dies tust, wird deine ursprüngliche Stimme überschrieben.',
	'securepoll-submit' => 'Stimme abgeben',
	'securepoll-gpg-receipt' => 'Vielen Dank.

Es folgt eine Bestätigung als Beweis für deine Stimmabgabe:

<pre>$1</pre>',
	'securepoll-thanks' => 'Vielen Dank, deine Stimme wurde gespeichert.',
	'securepoll-return' => 'Zurück zu $1',
	'securepoll-encrypt-error' => 'Beim Verschlüsseln deiner Stimme ist ein Fehler aufgetreten.
Deine Stimme wurde nicht gespeichert!

$1',
	'securepoll-no-gpg-home' => 'GPG-Benutzerverzeichnis kann nicht erstellt werden.',
	'securepoll-secret-gpg-error' => 'Fehler beim Ausführen von GPG.
$wgSecurePollShowErrorDetail=true; in LocalSettings.php einfügen, um mehr Details anzuzeigen.',
	'securepoll-full-gpg-error' => 'Fehler beim Ausführen von GPG:

Befehl: $1

Fehler:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG-Schlüssel sind nicht korrekt konfiguriert.',
	'securepoll-gpg-parse-error' => 'Fehler beim Interpretieren der GPG-Ausgabe.',
	'securepoll-no-decryption-key' => 'Es ist kein Entschlüsselungsschlüssel konfiguriert.
Entschlüsselung nicht möglich.',
	'securepoll-list-title' => 'Stimmen auflisten: $1',
	'securepoll-header-timestamp' => 'Zeit',
	'securepoll-header-user-name' => 'Name',
	'securepoll-header-user-domain' => 'Domäne',
	'securepoll-header-ua' => 'Benutzeragent',
	'securepoll-header-strike' => 'Streichen',
	'securepoll-header-details' => 'Details',
	'securepoll-strike-button' => 'Streichen',
	'securepoll-unstrike-button' => 'Streichung zurücknehmen',
	'securepoll-strike-reason' => 'Grund:',
	'securepoll-strike-cancel' => 'Abbrechen',
	'securepoll-strike-error' => 'Fehler bei der Streichung/Streichungsrücknahme: $1',
	'securepoll-need-admin' => 'Du musst ein Administrator sein, um diese Aktion durchzuführen.',
	'securepoll-details-link' => 'Details',
	'securepoll-details-title' => 'Abstimmungsdetails: #$1',
	'securepoll-invalid-vote' => '„$1“ ist keine gültige Abstimmungs-ID',
	'securepoll-header-user-type' => 'Benutzertyp',
	'securepoll-voter-properties' => 'Wählereigenschaften',
	'securepoll-strike-log' => 'Streichungs-Logbuch',
	'securepoll-header-action' => 'Aktion',
	'securepoll-header-reason' => 'Grund',
	'securepoll-header-admin' => 'Administrator',
	'securepoll-dump-title' => 'Auszug: $1',
	'securepoll-dump-no-crypt' => 'Für diese Wahl sind keine verschlüsselten Abstimmungsaufzeichnungen verfügbar, da die Wahl nicht für Verschlüsselung konfiguriert wurde.',
	'securepoll-dump-not-finished' => 'Verschlüsselte Abstimmungsaufzeichnungen sind nur nach dem Endtermin verfügbar: $1',
	'securepoll-dump-no-urandom' => '/dev/urandom kann nicht geöffnet werden.
Um den Wählerdatenschutz zu wahren, sind verschlüsselte Abstimmungsaufzeichnungen nur öffentlich verfügbar, wenn sie mit einem sicheren Zufallszahlenstrom gemischt werden können.',
	'securepoll-translate-title' => 'Übersetzen: $1',
	'securepoll-invalid-language' => 'Ungültiger Sprachcode „$1“',
	'securepoll-submit-translate' => 'Aktualisieren',
	'securepoll-language-label' => 'Sprache auswählen:',
	'securepoll-submit-select-lang' => 'Übersetzen',
);

/** German (formal address) (Deutsch (Sie-Form))
 * @author ChrisiPK
 */
$messages['de-formal'] = array(
	'securepoll-not-authorised' => 'Sie sind nicht berechtigt, in dieser Wahl abzustimmen.',
	'securepoll-not-qualified' => 'Sie sind nicht qualifiziert, in dieser Wahl abzustimmen: $1',
	'securepoll-change-disallowed' => 'Sie haben bereits in dieser Wahl abgestimmt.
Sie dürfen nicht erneut abstimmen.',
	'securepoll-change-allowed' => '<strong>Hinweis: Sie haben in dieser Wahl bereits abgestimmt.</strong>
Sie können Ihre Stimme ändern, indem Sie das untere Formular abschicken.
Wenn Sie dies tun, wird Ihre ursprüngliche Stimme überschrieben.',
	'securepoll-gpg-receipt' => 'Vielen Dank.

Es folgt eine Bestätigung als Beweis für Ihre Stimmabgabe:

<pre>$1</pre>',
	'securepoll-thanks' => 'Vielen Dank, Ihre Stimme wurde gespeichert.',
	'securepoll-encrypt-error' => 'Beim Verschlüsseln Ihrer Stimme ist ein Fehler aufgetreten.
Ihre Stimme wurde nicht gespeichert!

$1',
);

/** Lower Sorbian (Dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'securepoll' => 'Wěste wótgłosowanje',
	'securepoll-desc' => 'Rozšyrjenje za wólby a napšašowanja',
	'securepoll-invalid-page' => 'Njepłaśiwy pódbok "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Nic dosć pódbokowych parametrow (njepłaśiwy wótkaz)',
	'securepoll-invalid-election' => '"$1" njejo płaśiwy wólbny ID.',
	'securepoll-not-authorised' => 'Njejsy awtorizěrowany w toś tej wólbje wótgłosowaś.',
	'securepoll-welcome' => '<strong>Witaj $1!</strong>',
	'securepoll-not-started' => 'Toś ta wólba hyšći njejo se zachopiła.
Zachopijo nejskerjej $1.',
	'securepoll-not-qualified' => 'Njejsy wopšawnjony w toś tej wólbje wótgłosowaś: $1',
	'securepoll-change-disallowed' => 'Sy južo wótgłosował w toś tej wólbje.
Njesmějoš hyšći raz wótgłosowaś.',
	'securepoll-submit' => 'Głos daś',
	'securepoll-thanks' => 'Źěkujomy se, twój głos jo se zregistrěrował.',
	'securepoll-return' => 'Slědk k $1',
	'securepoll-gpg-config-error' => 'GPG-kluce su wopak konfigurěrowane.',
	'securepoll-no-decryption-key' => 'Dešifrěrowański kluc njejo konfigurěrowany.
Njejo móžno dešifrěrowaś.',
	'securepoll-list-title' => 'Głose nalicyś: $1',
	'securepoll-header-timestamp' => 'Cas',
	'securepoll-header-user-name' => 'Mě',
	'securepoll-header-user-domain' => 'Domena',
	'securepoll-header-ua' => 'Identifikator wobglědowaka',
	'securepoll-header-strike' => 'Wušmarnuś',
	'securepoll-header-details' => 'Drobnostki',
	'securepoll-strike-button' => 'Wušmarnuś',
	'securepoll-unstrike-button' => 'Wušmarnjenje anulěrowaś',
	'securepoll-strike-reason' => 'Pśicyna:',
	'securepoll-strike-cancel' => 'Pśetergnuś',
	'securepoll-need-admin' => 'Musyš administrator byś, aby pśewjadł toś tu akciju.',
	'securepoll-details-link' => 'Drobnostki',
	'securepoll-details-title' => 'Wótgłosowańske drobnostki: #$1',
	'securepoll-invalid-vote' => '"$1" njejo płaśiwy wótgłosowański ID',
	'securepoll-header-user-type' => 'Wužywarski typ',
	'securepoll-voter-properties' => 'Kakosći wólarja',
	'securepoll-strike-log' => 'Protokol wušmarnjenjow',
	'securepoll-header-action' => 'Akcija',
	'securepoll-header-reason' => 'Pśicyna',
	'securepoll-header-admin' => 'Administrator',
	'securepoll-dump-title' => 'Wuśěg: $1',
	'securepoll-translate-title' => 'Pśełožyś: $1',
	'securepoll-invalid-language' => 'Njepłaśiwy rěcny kod "$1"',
	'securepoll-submit-translate' => 'Aktualizěrowaś',
	'securepoll-language-label' => 'Rěc wubraś:',
	'securepoll-submit-select-lang' => 'Přełožyś',
);

/** Greek (Ελληνικά)
 * @author Consta
 * @author Crazymadlover
 * @author Geraki
 * @author ZaDiak
 */
$messages['el'] = array(
	'securepoll' => 'SecurePoll',
	'securepoll-desc' => 'Επέκταση για εκλογές και δημοσκοπήσεις',
	'securepoll-invalid-page' => 'Άκυρη υποσελίδα "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Μη αρκετές παράμετροι υποσελίδας (άκυρος σύνδεσμος).',
	'securepoll-invalid-election' => '"$1" δεν είναι ένα αποδεκτό ID ψηφοφορίας.',
	'securepoll-not-authorised' => 'Δεν έχετε δικαίωμα να ψηφίσετε σε αυτή την ψηφοφορία.',
	'securepoll-welcome' => '<strong>Καλωσήρθες $1!</strong>',
	'securepoll-not-started' => 'Η ψηφοφορία δεν έχει ξεκινήσει ακόμη.
Είναι προγραμματισμένη να ξεκινήσει στις $1.',
	'securepoll-not-qualified' => 'Δεν καλύπτετε τα κριτήρια για να ψηφίσετε σε αυτή την ψηφοφορία: $1',
	'securepoll-change-disallowed' => 'Έχετε ψηφίσει προηγουμένως σε αυτή την ψηφοφορία.
Συγνώμη, δεν μπορείτε να ψηφίσετε ξανά.',
	'securepoll-change-allowed' => '<strong>Σημείωση: Έχετε ψηφίσει προηγουμένως σε αυτή την ψηφοφορία.</strong>
Μπορείτε να αλλάξετε την ψήφο σας αποστέλλοντας την φόρμα παρακάτω.
Λάβετε υπόψη ότι αν κάνετε αυτό, η αρχική ψήφος σας θα ακυρωθεί.',
	'securepoll-submit' => 'Καταχώρηση ψήφου',
	'securepoll-gpg-receipt' => 'Ευχαριστούμε που ψηφίσατε.

Αν επιθυμείτε, μπορείτε να διατηρήσετε την παρακάτω απόδειξη ως πειστήριο της ψήφου σας:

<pre>$1</pre>',
	'securepoll-thanks' => 'Ευχαριστούμε, η ψήφος σας καταγράφηκε.',
	'securepoll-return' => 'Επιστροφή στην $1',
	'securepoll-encrypt-error' => 'Αποτυχία κρυπτογράφησης της καταγραφής ψήφου σας.
Η ψήφος σας δεν έχει καταγραφεί!

$1',
	'securepoll-no-gpg-home' => 'Αποτυχία δημιουργίας οικείου καταλόγου GPG.',
	'securepoll-secret-gpg-error' => 'Σφάλμα εκτέλεσης GPG. 
Χρησιμοποιήστε $wgSecurePollShowErrorDetail=true; στο LocalSettings.php για εμφάνιση περισσότερων λεπτομερειών.',
	'securepoll-full-gpg-error' => 'Σφάλμα εκτέλεσης GPG:

Εντολή: $1

Σφάλμα:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'Τα κλειδιά GPG είναι ρυθμισμένα λανθασμένα.',
	'securepoll-gpg-parse-error' => 'Σφάλμα διερμηνείας εξόδου GPG.',
	'securepoll-no-decryption-key' => 'Δεν έχει ρυθμιστεί κλειδί αποκρυπτογράφησης.
Δεν είναι δυνατή η αποκρυπτογράφηση.',
	'securepoll-header-timestamp' => 'Ώρα',
	'securepoll-header-user-name' => 'Όνομα',
	'securepoll-header-details' => 'Λεπτομέρειες',
	'securepoll-strike-reason' => 'Λόγος:',
	'securepoll-strike-cancel' => 'Άκυρο',
	'securepoll-details-link' => 'Λεπτομέρειες',
	'securepoll-invalid-vote' => 'Η "$1" δεν είναι μια έγκυρη ψήφος βάση ταυτότητας',
	'securepoll-header-user-type' => 'Τύπος χρήστη',
	'securepoll-header-action' => 'Ενέργεια',
	'securepoll-header-reason' => 'Λόγος',
	'securepoll-header-admin' => 'Διαχειριστής',
	'securepoll-translate-title' => 'Μετάφραση: $1',
	'securepoll-submit-translate' => 'Ενημέρωση',
	'securepoll-submit-select-lang' => 'Μετάφραση',
);

/** Esperanto (Esperanto)
 * @author Yekrats
 */
$messages['eo'] = array(
	'securepoll' => 'Sekura Enketo',
	'securepoll-desc' => 'Kromprogramo por voĉdonadoj kaj enketoj',
	'securepoll-invalid-page' => 'Nevalida subpaĝo "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Ne sufiĉaj subpaĝaj parametroj (nevalida ligilo).',
	'securepoll-invalid-election' => '"$1" ne estas valida voĉdonada identigo.',
	'securepoll-not-authorised' => 'Vi ne rajtas voĉdoni en ĉi tiu voĉdonado.',
	'securepoll-welcome' => '<strong>Bonvenon, $1!</strong>',
	'securepoll-not-started' => 'Ĉi tiu voĉdonado ne jam estis funkciata.
Ĝi pretos komenci je $1.',
	'securepoll-not-qualified' => 'Vi ne rajtas voĉdoni en ĉi tiu voĉdonado: $1',
	'securepoll-submit' => 'Enmeti voĉdonon',
	'securepoll-thanks' => 'Dankon, via voĉdono estis registrita.',
	'securepoll-return' => 'Reiri al $1',
	'securepoll-encrypt-error' => 'Malsukcesis enĉifri vian voĉdonan rekordon.
Via voĉdono ne estis rekordita!

$1',
	'securepoll-no-gpg-home' => 'Ne eblas krei GPG hejman dosierujon.',
	'securepoll-gpg-config-error' => 'GPG-ŝlosiloj estas konfiguritaj malĝuste.',
	'securepoll-header-timestamp' => 'Tempo',
	'securepoll-header-user-name' => 'Nomo',
	'securepoll-header-user-domain' => 'Domajno',
	'securepoll-header-details' => 'Detaloj',
	'securepoll-strike-reason' => 'Kialo:',
	'securepoll-strike-cancel' => 'Nuligi',
	'securepoll-details-link' => 'Detaloj',
	'securepoll-details-title' => 'Detaloj de voĉdono: #$1',
	'securepoll-header-action' => 'Ago',
	'securepoll-header-reason' => 'Kialo',
	'securepoll-header-admin' => 'Administranto',
	'securepoll-translate-title' => 'Traduki: $1',
	'securepoll-invalid-language' => 'Malvalida lingva kodo "$1"',
	'securepoll-submit-translate' => 'Ĝisdatigi',
	'securepoll-language-label' => 'Elekti lingvon:',
	'securepoll-submit-select-lang' => 'Traduki',
);

/** Spanish (Español)
 * @author Crazymadlover
 * @author Dferg
 * @author DoveBirkoff
 */
$messages['es'] = array(
	'securepoll' => 'SecurePoll',
	'securepoll-desc' => 'Extensiones para elecciones y encuentas',
	'securepoll-invalid-page' => 'Subpágina inválida "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Parámetros de subpágina insuficientes (vínculo inválido).',
	'securepoll-invalid-election' => '"$1" no es un identificador de elección valido.',
	'securepoll-not-authorised' => 'No estás autorizado para votar en esta elección.',
	'securepoll-welcome' => '<strong>Bienvenido $1!</strong>',
	'securepoll-not-started' => 'Esta elección aún no ha comenzado.
Está programada de comenzar en $1.',
	'securepoll-not-qualified' => 'No cumples los requisitos para votar en esta elección: $1',
	'securepoll-change-disallowed' => 'Ya has votado antes en esta elección.
Lo siento, no puede votar de nuevo.',
	'securepoll-change-allowed' => '<strong>Nota: Has votado en esta elección antes.</strong>
Puedes cambiar tu voto enviando el formulario de abajo.
Nota que si haces esto, tu voto original será descartado.',
	'securepoll-submit' => 'Enviar voto',
	'securepoll-gpg-receipt' => 'Gracias por votar.

Si deseas, puedes retener el siguiente comprobante como evidencia de tu voto:

<pre>$1</pre>',
	'securepoll-thanks' => 'Gracias, tu voto ha sido guardado.',
	'securepoll-return' => 'Retornar a $1',
	'securepoll-encrypt-error' => 'Fracasaste en encriptar tu registro de voto.
Tu voto no ha sido registrado!

$1',
	'securepoll-secret-gpg-error' => 'Error ejecutando GPG.
Usar $wgSecurePollShowErrorDetail=true; en LocalSettings.php para mostrar más detalle.',
	'securepoll-full-gpg-error' => 'Error ejecutando GPG:

Comando: $1

Error:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'Teclas GPG están configuradas incorrectamente.',
	'securepoll-gpg-parse-error' => 'Error interpretando salida GPG.',
	'securepoll-list-title' => 'Lista votos: $1',
	'securepoll-header-timestamp' => 'Tiempo',
	'securepoll-header-user-name' => 'Nombre',
	'securepoll-header-user-domain' => 'Dominio',
	'securepoll-header-ua' => 'Agente de usuario',
	'securepoll-header-strike' => 'Tachar',
	'securepoll-header-details' => 'Detalles',
	'securepoll-strike-button' => 'Trachar',
	'securepoll-strike-reason' => 'Razón:',
	'securepoll-strike-cancel' => 'Cancelar',
	'securepoll-need-admin' => 'Necesitas ser un administrador para realizar esta acción.',
	'securepoll-details-link' => 'Detalles',
	'securepoll-details-title' => 'Detalles de voto: #$1',
	'securepoll-header-user-type' => 'Tipo de usuario',
	'securepoll-voter-properties' => 'Propiedades de votante',
	'securepoll-header-action' => 'Acción',
	'securepoll-header-reason' => 'Razón',
	'securepoll-header-admin' => 'Administrador',
	'securepoll-dump-no-crypt' => 'No se dispone de un registro encriptado para esta votación dado que esta votación no ha sido configurada para usar encriptación.',
	'securepoll-dump-not-finished' => 'Los registros encriptados de la votación están únicamente disponibles después de la fecha de finalización: $1',
	'securepoll-translate-title' => 'Traducir: $1',
	'securepoll-invalid-language' => 'Código de lenguaje inválido "$1"',
	'securepoll-submit-translate' => 'Actualizar',
	'securepoll-language-label' => 'Seleccionar lenguaje:',
	'securepoll-submit-select-lang' => 'Traducir',
);

/** Estonian (Eesti)
 * @author WikedKentaur
 */
$messages['et'] = array(
	'securepoll-invalid-election' => '"$1" pole õige hääletuse-ID.',
	'securepoll-welcome' => '<strong>Tere tulemast $1!</strong>',
	'securepoll-not-started' => 'Hääletus pole veel alanud.
See algab $1.',
	'securepoll-change-disallowed' => 'Sa oled oma hääle juba andnud.
Teistkorda hääletada ei saa.',
	'securepoll-change-allowed' => '<strong>Teade: Sa oled oma hääle juba andnud.</strong>
Sa võid allpool oma antud häält muuta.
Kui sa seda teed, siis sinu eelmine hääl tühistub.',
	'securepoll-gpg-receipt' => 'Täname hääletamast.

Soovi korral võid talletada järgneva kinnituse antud hääle kohta:

<pre>$1</pre>',
	'securepoll-thanks' => 'Täname, sinu hääl on talletatud.',
	'securepoll-return' => 'Pöördu tagasi $1',
	'securepoll-encrypt-error' => 'Sinu antud hääle andmeid ei õnnestunud krüpteerida.
Sinu häält pole talletatud!

$1',
	'securepoll-header-timestamp' => 'Aeg',
	'securepoll-header-user-name' => 'Nimi',
	'securepoll-header-user-domain' => 'Domeen',
	'securepoll-header-details' => 'Üksikasjad',
	'securepoll-strike-reason' => 'Põhjus:',
	'securepoll-strike-cancel' => 'Katkesta',
	'securepoll-need-admin' => 'Selle tegevuse sooritamiseks pead sa olema administraator.',
	'securepoll-details-title' => 'Hääletuse andmed: #$1',
	'securepoll-invalid-vote' => '"$1" pole õige hääle-ID.',
	'securepoll-header-reason' => 'Põhjus',
	'securepoll-translate-title' => 'Tõlgi: $1',
	'securepoll-invalid-language' => 'Vigane keelekood  "$1"',
	'securepoll-submit-translate' => 'Uuenda',
	'securepoll-language-label' => 'Vali keel:',
	'securepoll-submit-select-lang' => 'Tõlgi',
);

/** Persian (فارسی)
 * @author Meisam
 */
$messages['fa'] = array(
	'securepoll' => 'رای‌گیری امن',
	'securepoll-desc' => 'افزونه برای رای‌گیری‌ها و جمع‌آوری اطلاعات',
	'securepoll-invalid-page' => 'زیرسفحه نامعتبر "<nowiki>$1</nowiki>"',
	'securepoll-not-qualified' => 'شما واجد شرایط شرکت در این رای‌گیری نیستید: $1',
	'securepoll-submit' => 'ارسال رای',
	'securepoll-return' => 'بازگشت به $1',
	'securepoll-header-timestamp' => 'زمان',
	'securepoll-header-user-name' => 'نام',
	'securepoll-header-user-domain' => 'دامین',
	'securepoll-header-details' => 'جزئیات',
	'securepoll-strike-reason' => 'دلیل:',
	'securepoll-details-link' => 'جزئیات',
	'securepoll-details-title' => 'جزییات رای: #$1',
	'securepoll-header-user-type' => 'نوع کاربر',
	'securepoll-header-reason' => 'دلیل',
	'securepoll-header-admin' => 'مدیر',
	'securepoll-submit-translate' => 'به‌روزآوری',
	'securepoll-language-label' => 'انتخاب زبان:',
	'securepoll-submit-select-lang' => 'ترجمه',
);

/** Finnish (Suomi)
 * @author Str4nd
 */
$messages['fi'] = array(
	'securepoll-desc' => 'Liitännäinen vaaleille ja kyselyille.',
	'securepoll-invalid-page' => 'Virheellinen alasivu ”<nowiki>$1</nowiki>”',
	'securepoll-welcome' => '<strong>Tervetuloa $1!</strong>',
	'securepoll-thanks' => 'Kiitos, äänesi on rekisteröity.',
	'securepoll-no-gpg-home' => 'GPG:n kotihakemistoa ei voitu luoda.',
	'securepoll-gpg-config-error' => 'GPG-avaimet ovat asetettu virheellisesti.',
	'securepoll-no-decryption-key' => 'Salauksen purkuavainta ei ole asetettu.
Salausta ei voitu purkaa.',
	'securepoll-header-timestamp' => 'Aika',
	'securepoll-header-user-name' => 'Nimi',
	'securepoll-header-user-domain' => 'Verkkotunnus',
	'securepoll-strike-reason' => 'Syy',
	'securepoll-strike-cancel' => 'Peruuta',
	'securepoll-voter-properties' => 'Äänestäjän asetukset',
	'securepoll-header-reason' => 'Syy',
	'securepoll-invalid-language' => 'Virheellinen kielikoodi ”$1”',
	'securepoll-submit-translate' => 'Päivitä',
	'securepoll-language-label' => 'Valitse kieli',
	'securepoll-submit-select-lang' => 'Käännä',
);

/** French (Français)
 * @author Crochet.david
 * @author IAlex
 */
$messages['fr'] = array(
	'securepoll' => 'Sondage sécurisé',
	'securepoll-desc' => 'Extension pour des élections et sondages',
	'securepoll-invalid-page' => 'Sous-page « <nowiki>$1</nowiki> » invalide',
	'securepoll-too-few-params' => 'Pas assez de paramètres de sous-page (lien invalide).',
	'securepoll-invalid-election' => "« $1 » n'est pas un identifiant d'élection valide.",
	'securepoll-not-authorised' => "Vous n'êtes pas autorisé à voter pour cette élection.",
	'securepoll-welcome' => '<strong>Bienvenu $1 !</strong>',
	'securepoll-not-started' => "L'élection n'a pas encore commencé.
Elle débutera le $1.",
	'securepoll-not-qualified' => "Vous n'êtes pas qualifié pour voter dans cette élection : $1",
	'securepoll-change-disallowed' => 'Vous avez déjà voté pour cette élection.
Désolé, vous ne pouvez pas voter à nouveau.',
	'securepoll-change-allowed' => '<strong>Note : Vous avez déjà voté pour cette élection.</strong>
Vous pouvez changer votre vote en soumettant le formulaire ci-dessous.
Si vous faites ceci, votre ancien vote sera annulé.',
	'securepoll-submit' => 'Soumettre le vote',
	'securepoll-gpg-receipt' => 'Merci de votre vote.

Si vous le désirez, vous pouvez garder ceci comme preuve de votre vote :

<pre>$1</pre>',
	'securepoll-thanks' => 'Merci, votre vote a été enregistré.',
	'securepoll-return' => 'Revenir à $1',
	'securepoll-encrypt-error' => "Le cryptage de votre vote a échoué.
Votre vote n'a pas été enregistré !

$1",
	'securepoll-no-gpg-home' => 'Impossible de créer le dossier de base de GPG.',
	'securepoll-secret-gpg-error' => 'Erreur lors de l\'exécution de GPG.
Ajoutez $wgSecurePollShowErrorDetail=true; à LocalSettings.php pour afficher plus de détails.',
	'securepoll-full-gpg-error' => "Erreur lors de l'exécution de GPG :

Commande : $1

Erreur :
<pre>$2</pre>",
	'securepoll-gpg-config-error' => 'Les clés de GPG ne sont pas correctement configurées.',
	'securepoll-gpg-parse-error' => "Erreur lors de l'interprétation de la sortie de GPG.",
	'securepoll-no-decryption-key' => "Aucune clé de décryptage n'a été configurée.
Impossible de décrypter.",
	'securepoll-list-title' => 'Liste des votes : $1',
	'securepoll-header-timestamp' => 'Heure',
	'securepoll-header-user-name' => 'Nom',
	'securepoll-header-user-domain' => 'Domaine',
	'securepoll-header-ua' => 'Agent utilisateur',
	'securepoll-header-strike' => 'Biffer',
	'securepoll-header-details' => 'Détails',
	'securepoll-strike-button' => 'Biffer',
	'securepoll-unstrike-button' => 'Débiffer',
	'securepoll-strike-reason' => 'Raison :',
	'securepoll-strike-cancel' => 'Annuler',
	'securepoll-strike-error' => 'Erreur lors du (dé)biffage : $1',
	'securepoll-need-admin' => 'Vous devez être un administrateur pour exécuter cette action.',
	'securepoll-details-link' => 'Détails',
	'securepoll-details-title' => 'Détails du vote : #$1',
	'securepoll-invalid-vote' => '« $1 » n’est pas un vote ID valide',
	'securepoll-header-user-type' => "Type de l'utilisateur",
	'securepoll-voter-properties' => 'Propriétés du votant',
	'securepoll-strike-log' => 'Journal des biffages',
	'securepoll-header-action' => 'Action',
	'securepoll-header-reason' => 'Raison',
	'securepoll-header-admin' => 'Administrateur',
	'securepoll-dump-no-crypt' => "Les données non encryptées sont disponible pour cette élection, car l'élection n'a pas été configuré pour utiliser de cryptage",
	'securepoll-dump-not-finished' => "Les données cryptées ne sont disponibles qu'après la clôture de l'élection : $1",
	'securepoll-dump-no-urandom' => "Impossible d'ouvrir /dev/urandom.
Pour maintenir la confidentialité des votants, les données cryptées ne sont disponibles que si elles peuvent être brouillées avec un nombre de caractères aléatoires.",
	'securepoll-translate-title' => 'Traduire : $1',
	'securepoll-invalid-language' => 'Code de langue « $1 » invalide.',
	'securepoll-submit-translate' => 'Mettre à jour',
	'securepoll-language-label' => 'Sélectionner la langue :',
	'securepoll-submit-select-lang' => 'Traduire',
);

/** Irish (Gaeilge)
 * @author Stifle
 */
$messages['ga'] = array(
	'securepoll-not-started' => 'Níl an toghchán seo tosaithe fós.
De réir an sceidil, tosnóidh sé ag $1.',
	'securepoll-not-qualified' => 'Níl tú cáilithe vótáil sa thoghchán seo: $1',
	'securepoll-change-disallowed' => 'Vótail tú sa thoghchán seo roimhe seo.
Níl cead agat vótáil arís.',
);

/** Galician (Galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'securepoll' => 'Sondaxe de seguridade',
	'securepoll-desc' => 'Extensión para as eleccións e sondaxes',
	'securepoll-invalid-page' => 'Subpáxina "<nowiki>$1</nowiki>" inválida',
	'securepoll-too-few-params' => 'Non hai parámetros de subpáxina suficientes (ligazón inválida).',
	'securepoll-invalid-election' => '"$1" non é un número de identificación das eleccións válido.',
	'securepoll-not-authorised' => 'Non está autorizado para votar nestas eleccións.',
	'securepoll-welcome' => '<strong>Dámoslle a benvida, $1!</strong>',
	'securepoll-not-started' => 'Estas eleccións aínda non comezaron.
Están programadas para empezar o $1.',
	'securepoll-not-qualified' => 'Non está cualificado para votar nestas eleccións: $1',
	'securepoll-change-disallowed' => 'Xa votou nestas eleccións.
Sentímolo, non pode votar de novo.',
	'securepoll-change-allowed' => '<strong>Nota: xa votou nestas eleccións.</strong>
Pode cambiar o seu voto enviando o formulario de embaixo.
Déase conta de que se fai isto o seu voto orixinal será descartado.',
	'securepoll-submit' => 'Enviar o voto',
	'securepoll-gpg-receipt' => 'Grazas por votar.

Se o desexa, pode gardar o seguinte recibo como proba do seu voto:

<pre>$1</pre>',
	'securepoll-thanks' => 'Grazas, o seu voto foi rexistrado.',
	'securepoll-return' => 'Voltar a $1',
	'securepoll-encrypt-error' => 'Non se puido encriptar o rexistro do seu voto.
O seu voto non foi gardado!

$1',
	'securepoll-no-gpg-home' => 'Non se pode crear directorio principal GPG.',
	'securepoll-secret-gpg-error' => 'Erro ao executar o directorio GPG.
Use $wgSecurePollShowErrorDetail=true; en LocalSettings.php para obter máis detalles.',
	'securepoll-full-gpg-error' => 'Erro ao executar o directorio GPG:

Comando: $1

Erro:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'As chaves GPG están configuradas incorrectamente.',
	'securepoll-gpg-parse-error' => 'Erro de interpretación do GPG de saída.',
	'securepoll-no-decryption-key' => 'Non hai unha chave de desencriptar configurada.
Non se pode desencriptar.',
);

/** Swiss German (Alemannisch)
 * @author Als-Holder
 */
$messages['gsw'] = array(
	'securepoll' => 'Sicheri Abstimmig',
	'securepoll-desc' => 'Erwyterig fir Wahlen un Umfroge',
	'securepoll-invalid-page' => 'Nit giltigi Untersyte „<nowiki>$1</nowiki>“',
	'securepoll-too-few-params' => 'Nit gnue Untersyteparameter (nit giltig Gleich).',
	'securepoll-invalid-election' => '„$1“ isch kei giltigi Abstimmigs-ID.',
	'securepoll-not-authorised' => 'Du derfsch in däre Wahl nit abstimme.',
	'securepoll-welcome' => '<strong>Willchuu $1!</strong>',
	'securepoll-not-started' => 'Die Wahl het nonig aagfange.
Si fangt wahrschyns aa am $1.',
	'securepoll-not-qualified' => 'Du bisch nit qualifiziert zum in däre Wahl abzstimme: $1',
	'securepoll-change-disallowed' => 'Du hesch in däre Wahl scho abgstimmt.
Du derfsch nit nomol abstimme.',
	'securepoll-change-allowed' => '<strong>Hiiwys: Du hesch in däre Wahl scho abgstimmt.</strong>
Du chasch Dyyn Stimm ändere, indäm s unter Formular abschicksch.
Wänn Du des machsch, wird Dyy urspringligi Stimm iberschribe.',
	'securepoll-submit' => 'Stimm abgee',
	'securepoll-gpg-receipt' => 'Dankschen.

S chunnt e Bstetigung as Bewyys fir Dyy Stimmabgab:

<pre>$1</pre>',
	'securepoll-thanks' => 'Dankschen, Dyy Stimm isch gspycheret wore.',
	'securepoll-return' => 'Zruck zue $1',
	'securepoll-encrypt-error' => 'Bim Verschlissle vu Dyynere Stimm het s e Fähler gee.
Dyy Stimm isch nit gspycheret wore!

$1',
	'securepoll-no-gpg-home' => 'GPG-Heimverzeichnis cha nit aagleit wäre.',
	'securepoll-secret-gpg-error' => 'Fähler bim Uusfiere vu GPG.
$wgSecurePollShowErrorDetail=true; in LocalSettings.php yyfiege go meh Detail aazeige.',
	'securepoll-full-gpg-error' => 'Fähler bim Uusfiere vu GPG:

Befähl: $1

Fähler:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG-Schlissel sin nit korrekt konfiguriert.',
	'securepoll-gpg-parse-error' => 'Fähler bim Interpretiere vu dr Uusgab vu GPG.',
	'securepoll-no-decryption-key' => 'S isch kei Entschlisseligsschlissel konfiguriert.
Entschlisselig nit megli.',
);

/** Hebrew (עברית)
 * @author Rotem Liss
 */
$messages['he'] = array(
	'securepoll' => 'הצבעה מאובטחת',
	'securepoll-desc' => 'הרחבה המאפשרת הצבעות וסקרים',
	'securepoll-invalid-page' => 'דף משנה בלתי תקין: "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'אין מספיק פרמטרים של דפי משנה (קישור בלתי תקין).',
	'securepoll-invalid-election' => '"$1" אינו מספר הצבעה תקין.',
	'securepoll-not-authorised' => 'אינכם מורשים להצביע בהצבעה זו.',
	'securepoll-welcome' => '<strong>ברוכים הבאים, $1!</strong>',
	'securepoll-not-started' => 'הצבעה זו טרם התחילה.
היא מיועדת להתחיל ב־$1.',
	'securepoll-not-qualified' => 'אינכם רשאים להצביע בהצבעה זו: $1',
	'securepoll-change-disallowed' => 'הצבעתם כבר בהצבעה זו.
מצטערים, אינכם רשאים להצביע שוב.',
	'securepoll-change-allowed' => '<strong>הערה: כבר הצבעתם בהצבעה זו בעבר.</strong>
באפשרותכם לשנות את הצבעתכם באמצעות שליחת הטופס להלן.
אם תעשו זאת, הצבעתכם המקורית תימחק.',
	'securepoll-submit' => 'שליחת ההצבעה',
	'securepoll-gpg-receipt' => 'תודה על ההצבעה.

אם תרצו, תוכלו לשמור את הקבלה הבאה כהוכחה להצבעתכם:

<pre>$1</pre>',
	'securepoll-thanks' => 'תודה לכם, הצבעתכם נרשמה.',
	'securepoll-return' => 'בחזרה ל$1',
	'securepoll-encrypt-error' => 'הצפנת רשומת ההצבעה שלכם לא הצליחה.
הצבעתכם לא נרשמה!

$1',
	'securepoll-no-gpg-home' => 'לא ניתן ליצור את תיקיית הבית של GPG.',
	'securepoll-secret-gpg-error' => 'שגיאה בהרצת GPG.
הגדירו את $wgSecurePollShowErrorDetail=true; בקובץ LocalSettings.php להצגת פרטים נוספים.',
	'securepoll-full-gpg-error' => 'שגיאה בהרצת GPG:

פקודה: $1

שגיאה:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'מפתחות GPG אינם מוגדרים כהלכה.',
	'securepoll-gpg-parse-error' => 'שגיאה בפענוח הפלט של GPG.',
	'securepoll-no-decryption-key' => 'לא הוגדר מפתח פיענוח.
לא ניתן לפענח.',
	'securepoll-list-title' => 'רשימת הצבעות: $1',
	'securepoll-header-timestamp' => 'זמן',
	'securepoll-header-user-name' => 'שם',
	'securepoll-header-user-domain' => 'דומיין',
	'securepoll-header-ua' => 'זיהוי דפדפן',
	'securepoll-header-strike' => 'מחיקה',
	'securepoll-header-details' => 'פרטים',
	'securepoll-strike-button' => 'מחיקה',
	'securepoll-unstrike-button' => 'ביטול מחיקה',
	'securepoll-strike-reason' => 'סיבה:',
	'securepoll-strike-cancel' => 'ביטול',
	'securepoll-strike-error' => 'שגיאה בביצוע הסתרה או בביטול הסתרה: $1',
	'securepoll-need-admin' => 'עליכם להיות מנהלי ההצבעה כדי לבצע פעולה זו.',
	'securepoll-details-link' => 'פרטים',
	'securepoll-details-title' => 'פרטי ההצבעה: #$1',
	'securepoll-invalid-vote' => '"$1" אינו מספר הצבעה תקין',
	'securepoll-header-id' => 'מספר',
	'securepoll-header-user-type' => 'סוג משתמש',
	'securepoll-voter-properties' => 'מאפייני מצביע',
	'securepoll-strike-log' => 'יומן הסתרת הצבעות',
	'securepoll-header-action' => 'פעולה',
	'securepoll-header-reason' => 'סיבה',
	'securepoll-header-admin' => 'מנהל',
	'securepoll-dump-title' => 'העתק מוצפן: $1',
	'securepoll-dump-no-crypt' => 'לא נמצאה רשומת הצבעה מוצפנת עבור הצבעה זו, כיוון שההצבעה אינה מוגדרת לשימוש בהצפנה.',
	'securepoll-dump-not-finished' => 'רשומות ההצבעה המוצפנות זמינות רק לאחר תאריך הסיום: $1',
	'securepoll-dump-no-urandom' => 'לא ניתן לפתוח את /dev/urandom. 
כדי לשמור על פרטיות המצביעים, רשומות ההצבעה המוצפנות זמינותת באופן ציבורי רק כאשר ניתן לערבב אותן באמצעות זרם המשתמש במספר אקראי מאובטח.',
	'securepoll-translate-title' => 'תרגום: $1',
	'securepoll-invalid-language' => 'קוד שפה בלתי תקין "$1"',
	'securepoll-header-trans-id' => 'מספר',
	'securepoll-submit-translate' => 'עדכון',
	'securepoll-language-label' => 'בחירת שפה:',
	'securepoll-submit-select-lang' => 'תרגום',
);

/** Upper Sorbian (Hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'securepoll' => 'Wěste hłosowanje',
	'securepoll-desc' => 'Rozšěrjenje za wólby a naprašniki',
	'securepoll-invalid-page' => 'Njepłaćiwa podstrona "<nowiki>$1</nowiki>',
	'securepoll-too-few-params' => 'Nic dosć parametrow podstrony (njepłaćiwy wotkaz).',
	'securepoll-invalid-election' => '"$1" płaćiwy wólbny ID njeje.',
	'securepoll-not-authorised' => 'Njejsy woprawnjeny w tutej wólbje hłoasować.',
	'securepoll-welcome' => '<strong>Witaj $1!</strong>',
	'securepoll-not-started' => 'Wólba hišće njeje započała.
Započnje najskerje $1.',
	'securepoll-not-qualified' => 'Njejsy woprawnjeny w tutej wólbje hłosować: $1',
	'securepoll-change-disallowed' => 'Sy hižo w tutej wólbje wothłosował.
Njesměš znowa wothłosować.',
	'securepoll-submit' => 'Hłós wotedać',
	'securepoll-thanks' => 'Dźakujemy so, twój hłós bu zregistrowany.',
	'securepoll-return' => 'Wróćo k $1',
	'securepoll-gpg-config-error' => 'GPG-kluče su wopak konfigurowane.',
	'securepoll-no-decryption-key' => 'Žadyn dešifrowanski kluč konfigurowany.
Dešifrowanje njemóžno.',
	'securepoll-list-title' => 'Hłosy nalistować: $1',
	'securepoll-header-timestamp' => 'Čas',
	'securepoll-header-user-name' => 'Mjeno',
	'securepoll-header-user-domain' => 'Domena',
	'securepoll-header-ua' => 'Identifikator wobhladowaka',
	'securepoll-header-strike' => 'Šmórnyć',
	'securepoll-header-details' => 'Podrobnosće',
	'securepoll-strike-button' => 'Šmórnyć',
	'securepoll-unstrike-button' => 'Šmórnjenje cofnyć',
	'securepoll-strike-reason' => 'Přičina:',
	'securepoll-strike-cancel' => 'Přetorhnyć',
	'securepoll-need-admin' => 'Dyrbiš administrator być, zo by tutu akciju přewjedł.',
	'securepoll-details-link' => 'Podrobnosće',
	'securepoll-details-title' => 'Podrobnosće hłosowanja: #$1',
	'securepoll-invalid-vote' => '"$1" płaćiwy hłosowanski ID njeje.',
	'securepoll-header-user-type' => 'Wužiwarski typ',
	'securepoll-voter-properties' => 'Kajkosće wolerja',
	'securepoll-strike-log' => 'Protokol šmórnjenjow',
	'securepoll-header-action' => 'Akcija',
	'securepoll-header-reason' => 'Přičina',
	'securepoll-header-admin' => 'Administrator',
	'securepoll-dump-title' => 'Wućah: $1',
	'securepoll-translate-title' => 'Přełožić: $1',
	'securepoll-invalid-language' => 'Njepłaćiwy rěčny kod "$1"',
	'securepoll-submit-translate' => 'Aktualizować',
	'securepoll-language-label' => 'Rěč wubrać:',
	'securepoll-submit-select-lang' => 'Přełožić',
);

/** Hungarian (Magyar)
 * @author Bdamokos
 */
$messages['hu'] = array(
	'securepoll' => 'BiztonságosSzavazás',
	'securepoll-desc' => 'Kiegészítő választások és közvéleménykutatások lebonyolítására',
	'securepoll-invalid-page' => 'Érvénytelen allap: „"<nowiki>$1</nowiki>"”',
	'securepoll-too-few-params' => 'Nincs elég paraméter az allaphoz (érvénytelen hivatkozás).',
	'securepoll-invalid-election' => '„$1” nem érvényes választási azonosító.',
	'securepoll-not-authorised' => 'Nincs jogosultságod szavazni ezen a választáson.',
	'securepoll-welcome' => '<strong>Üdvözlünk $1!</strong>',
	'securepoll-not-started' => 'Ez a választás még nem kezdődött el.
Tervezett indulása: $1.',
	'securepoll-not-qualified' => 'Nincs jogod szavazni ezen a választáson: $1',
);

/** Interlingua (Interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'securepoll' => 'Voto secur',
	'securepoll-desc' => 'Extension pro electiones e inquestas',
	'securepoll-invalid-page' => 'Subpagina  "<nowiki>$1</nowiki>" invalide',
	'securepoll-too-few-params' => 'Non satis de parametros del subpagina (ligamine invalide).',
	'securepoll-invalid-election' => '"$1" non es un identificator valide de un election.',
	'securepoll-not-authorised' => 'Tu non es autorisate a votar in iste election.',
	'securepoll-welcome' => '<strong>Benvenite, $1!</strong>',
	'securepoll-not-started' => 'Iste election non ha ancora comenciate.
Le initio es programmate pro le $1.',
	'securepoll-not-qualified' => 'Tu non es qualificate pro votar in iste election: $1',
	'securepoll-change-disallowed' => 'Tu ha ja votate in iste election.
Non es possibile votar de novo.',
	'securepoll-change-allowed' => '<strong>Nota: Tu ha ja votate in iste election.</strong>
Tu pote cambiar tu voto per submitter le formulario in basso.
Nota que si tu face isto, le voto original essera cancellate.',
	'securepoll-submit' => 'Submitter voto',
	'securepoll-gpg-receipt' => 'Gratias pro votar.

Si tu vole, tu pote retener le sequente recepta como prova de tu voto:

<pre>$1</pre>',
	'securepoll-thanks' => 'Gratias, tu voto ha essite registrate.',
	'securepoll-return' => 'Retornar a $1',
	'securepoll-encrypt-error' => 'Impossibile cryptar le registro de tu voto.
Tu voto non ha essite registrate!

$1',
	'securepoll-no-gpg-home' => 'Impossibile crear le directorio de base pro GPG.',
	'securepoll-secret-gpg-error' => 'Error durante le execution de GPG.
Usa $wgSecurePollShowErrorDetail=true; in LocalSettings.php pro revelar plus detalios.',
	'securepoll-full-gpg-error' => 'Error durante le execution de GPG:

Commando: $1

Error:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'Le claves GPG non es configurate correctemente.',
	'securepoll-gpg-parse-error' => 'Error durante le interpretation del output de GPG.',
	'securepoll-no-decryption-key' => 'Nulle clave de decryptation es configurate.
Impossibile decryptar.',
	'securepoll-list-title' => 'Lista del votos: $1',
	'securepoll-header-timestamp' => 'Tempore',
	'securepoll-header-user-name' => 'Nomine',
	'securepoll-header-user-domain' => 'Dominio',
	'securepoll-header-ua' => 'Agente usator',
	'securepoll-header-strike' => 'Cancellation',
	'securepoll-header-details' => 'Detalios',
	'securepoll-strike-button' => 'Cancellar voto',
	'securepoll-unstrike-button' => 'Restaurar voto',
	'securepoll-strike-reason' => 'Motivo:',
	'securepoll-strike-cancel' => 'Annullar',
	'securepoll-strike-error' => 'Error durante le cancellation/restauration: $1',
	'securepoll-need-admin' => 'Tu debe esser un administrator pro poter executar iste action.',
	'securepoll-details-link' => 'Detalios',
	'securepoll-details-title' => 'Detalios del voto: #$1',
	'securepoll-invalid-vote' => '"$1" non es un identificator valide de un voto',
	'securepoll-header-user-type' => 'Typo de usator',
	'securepoll-voter-properties' => 'Proprietates del votator',
	'securepoll-strike-log' => 'Registro de cancellationes',
	'securepoll-header-action' => 'Action',
	'securepoll-header-reason' => 'Motivo',
	'securepoll-header-admin' => 'Admin',
);

/** Indonesian (Bahasa Indonesia)
 * @author Rex
 */
$messages['id'] = array(
	'securepoll-desc' => 'Ekstensi untuk pemungutan suara dan survei',
	'securepoll-invalid-page' => 'Subhalaman tidak sah "<nowiki>$1</nowiki>"',
	'securepoll-invalid-election' => 'ID pemilihan tidak sah: "$1"',
	'securepoll-not-authorised' => 'Anda tidak memiliki hak untuk memberikan suara dalam pemilihan ini.',
	'securepoll-welcome' => '<strong>Selamat datang $1!</strong>',
	'securepoll-not-started' => 'Pemungutan suara ini belum dimulai
dan baru akan berlangsung pada $1.',
	'securepoll-not-qualified' => 'Anda belum memenuhi syarat untuk memberikan suara dalam pemungutan suara ini: $1',
	'securepoll-change-disallowed' => 'Anda telah memberikan suara dalam pemilihan ini sebelumnya.
Maaf, Anda tidak dapat memberikan suara lagi.',
	'securepoll-submit' => 'Kirim suara',
);

/** Italian (Italiano)
 * @author BrokenArrow
 * @author Darth Kule
 * @author Melos
 */
$messages['it'] = array(
	'securepoll' => 'SecurePoll',
	'securepoll-desc' => 'Estensione per le elezioni e le indagini',
	'securepoll-invalid-page' => 'Sottopagina non valida "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Parametri della sottopagina non sufficienti (collegamento non valido).',
	'securepoll-invalid-election' => '"$1" non è un ID valido per l\'elezione.',
	'securepoll-not-authorised' => 'Non sei autorizzato a votare in questa elezione.',
	'securepoll-welcome' => '<strong>Benvenuto $1!</strong>',
	'securepoll-not-started' => "L'elezione non è ancora iniziata.
L'inizio è programmato per $1.",
	'securepoll-not-qualified' => 'Non sei qualificato per votare in questa elezione: $1',
	'securepoll-change-disallowed' => 'Hai già votato in questa elezione.
Non è possibile votare nuovamente.',
	'securepoll-change-allowed' => '<strong>Nota: hai già votato in questa elezione.</strong>
È possibile modificare il voto compilando il modulo seguente.
Si noti che così facendo il voto originale verrà scartato.',
	'securepoll-submit' => 'Invia il voto',
	'securepoll-gpg-receipt' => 'Grazie per aver votato.

È possibile mantenere la seguente ricevuta come prova della votazione:

<pre>$1</pre>',
	'securepoll-thanks' => 'Grazie, il tuo voto è stato registrato.',
	'securepoll-return' => 'Torna a $1',
	'securepoll-encrypt-error' => 'Impossibile cifrare le informazioni di voto.
Il voto non è stato registrato.

$1',
	'securepoll-no-gpg-home' => 'Impossibile creare la directory principale di GPG.',
	'securepoll-secret-gpg-error' => 'Errore durante l\'esecuzione di GPG.
Usare $wgSecurePollShowErrorDetail=true; in LocalSettings.php per mostrare maggiori dettagli.',
	'securepoll-full-gpg-error' => "Errore durante l'esecuzione di GPG:

Comando: $1

Errore:
<pre> $2 </pre>",
	'securepoll-gpg-config-error' => 'Le chiavi GPG non sono configurate correttamente.',
	'securepoll-gpg-parse-error' => "Errore nell'interpretazione dell'output di GPG.",
	'securepoll-no-decryption-key' => 'Nessuna chiave di decrittazione è configurata.
Impossibile decifrare.',
);

/** Japanese (日本語)
 * @author Aotake
 * @author Fryed-peach
 */
$messages['ja'] = array(
	'securepoll' => '暗号投票',
	'securepoll-desc' => '選挙と意識調査のための拡張機能',
	'securepoll-invalid-page' => '「<nowiki>$1</nowiki>」は無効なサブページです',
	'securepoll-too-few-params' => 'サブページ引数が足りません（リンクが無効です）。',
	'securepoll-invalid-election' => '「$1」は有効な選挙IDではありません。',
	'securepoll-not-authorised' => 'あなたはこの選挙に投票する権限がありません。',
	'securepoll-welcome' => '<strong>$1さん、ようこそ！</strong>',
	'securepoll-not-started' => 'この選挙はまだ始まっていません。$1 に開始する予定です。',
	'securepoll-not-qualified' => 'あなたはこの選挙に投票する資格がありません: $1',
	'securepoll-change-disallowed' => 'あなたはこの選挙で既に投票しています。申し訳ありませんが、二度の投票はできません。',
	'securepoll-change-allowed' => '<strong>注: あなたはこの選挙で既に投票しています。</strong>下のフォームから投稿することで票を変更できます。これを行う場合、以前の票は破棄されることに留意してください。',
	'securepoll-submit' => '投票',
	'securepoll-gpg-receipt' => '投票ありがとうございます。

必要ならば、以下の受理証をあなたの投票の証しとしてとっておくことができます。

<pre>$1</pre>',
	'securepoll-thanks' => 'ありがとうございます。あなたの投票は記録されました。',
	'securepoll-return' => '$1 に戻る',
	'securepoll-encrypt-error' => 'あなたの投票記録の暗号化に失敗しました。あなたの投票は記録されませんでした。

$1',
	'securepoll-no-gpg-home' => 'GPG ホームディレクトリが作成できません。',
	'securepoll-secret-gpg-error' => 'GPG の実行に失敗しました。より詳しい情報を表示するには、LocalSettings.php で $wgSecurePollShowErrorDetail=true; としてください。',
	'securepoll-full-gpg-error' => 'GPG の実行に失敗しました:

コマンド: $1

エラー:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG 鍵の設定が誤っています。',
	'securepoll-gpg-parse-error' => 'GPG 出力の解釈に失敗しました。',
	'securepoll-no-decryption-key' => '復号鍵が設定されておらず、復号できません。',
	'securepoll-list-title' => '票を一覧する： $1',
	'securepoll-header-timestamp' => '時刻',
	'securepoll-header-user-name' => '名前',
	'securepoll-header-user-domain' => 'ドメイン',
	'securepoll-header-ua' => 'ユーザーエージェント',
	'securepoll-header-details' => '詳細',
	'securepoll-strike-reason' => '理由:',
	'securepoll-strike-cancel' => 'キャンセル',
	'securepoll-need-admin' => 'この操作を行うには管理者権限が必要です。',
	'securepoll-details-link' => '詳細',
	'securepoll-details-title' => '票の詳細: #$1',
	'securepoll-invalid-vote' => '"$1"は有効な票IDではありません',
	'securepoll-header-user-type' => 'ユーザーのタイプ',
	'securepoll-voter-properties' => '投票者情報',
	'securepoll-header-reason' => '理由',
	'securepoll-header-admin' => '管理者',
);

/** Korean (한국어)
 * @author Kwj2772
 * @author Yknok29
 */
$messages['ko'] = array(
	'securepoll' => '비밀 투표',
	'securepoll-desc' => '선거와 여론 조사를 위한 확장 기능',
	'securepoll-invalid-page' => '"<nowiki>$1</nowiki>" 하위 문서가 잘못되었습니다.',
	'securepoll-too-few-params' => '하위 문서 변수가 충분하지 않습니다 (잘못된 링크).',
	'securepoll-invalid-election' => '"$1"은 유효한 선거 ID가 아닙니다.',
	'securepoll-not-authorised' => '당신은 이 선거에서 투표할 수 없습니다.',
	'securepoll-welcome' => '<strong>$1님, 환영합니다!</strong>',
	'securepoll-not-started' => '투표가 아직 시작되지 않았습니다.
투표는 $1부터 시작될 예정입니다.',
	'securepoll-not-qualified' => '당신에게는 이번 선거에서 투표권이 부여되지 않았습니다: $1',
	'securepoll-change-disallowed' => '당신은 이미 투표하였습니다.
죄송하지만 다시 투표할 수 없습니다.',
	'securepoll-change-allowed' => '<strong>참고: 당신은 이전에 투표한 적이 있습니다.</strong>
당신은 아래 양식을 이용해 투표를 변경할 수 있습니다.
그렇게 할 경우 이전의 투표는 무효 처리될 것입니다.',
	'securepoll-submit' => '투표하기',
	'securepoll-gpg-receipt' => '투표해 주셔서 감사합니다.

당신이 원하신다면 당신의 투표에 대한 증거로 다음 투표증을 보관할 수 있습니다:

<pre>$1</pre>',
	'securepoll-thanks' => '감사합니다. 당신의 투표가 기록되었습니다.',
	'securepoll-return' => '$1로 돌아가기',
	'securepoll-encrypt-error' => '당신의 투표를 암호화하는 데 실패했습니다.
당신의 투표가 기록되지 않았습니다.

$1',
	'securepoll-no-gpg-home' => 'GPG 홈 디렉토리를 생성할 수 없습니다.',
	'securepoll-secret-gpg-error' => 'GPG를 실행하는 데 오류가 발생하였습니다.
자세한 정보를 보려면 LocalSettings.php에 $wgSecurePollShowErrorDetail=true; 를 사용하십시오.',
	'securepoll-full-gpg-error' => 'GPG를 실행하는 데 오류가 발생하였습니다.

명령: $1

오류:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG 키가 잘못 설정되었습니다.',
	'securepoll-gpg-parse-error' => 'GPG 출력을 해석하는 데 오류가 발생했습니다.',
	'securepoll-no-decryption-key' => '암호 해독 키가 설정되지 않았습니다.
암호를 해독할 수 없습니다.',
	'securepoll-list-title' => '투표 목록: $1',
	'securepoll-header-timestamp' => '기간',
	'securepoll-header-user-name' => '이름',
	'securepoll-header-user-domain' => '도메인',
	'securepoll-header-ua' => '사용자 선거 사무장',
	'securepoll-header-strike' => '결산',
	'securepoll-header-details' => '상세한 설명',
	'securepoll-strike-button' => '결산',
	'securepoll-unstrike-button' => '미결산',
	'securepoll-strike-reason' => '이유:',
	'securepoll-strike-cancel' => '취소',
	'securepoll-strike-error' => '결산/미결산에 오류가 있었습니다: $1',
	'securepoll-need-admin' => '이 행동을 하시려면 관리자 권한이 필요합니다.',
	'securepoll-details-link' => '상세한 설명',
	'securepoll-details-title' => '투표 설명: #$1',
	'securepoll-invalid-vote' => '"$1"은 투표할 수 있는 ID가 아닙니다.',
	'securepoll-header-user-type' => '사용자 유형',
	'securepoll-voter-properties' => '투표자 특성',
	'securepoll-strike-log' => '결산 로그',
	'securepoll-header-action' => '참여',
	'securepoll-header-reason' => '이유',
	'securepoll-header-admin' => '관리',
	'securepoll-dump-title' => '출력: $1',
	'securepoll-dump-no-crypt' => '이 선거에 대한 기록을 암호화할 수 없습니다. 왜냐하면 이 선거는 암호를 사용하도록 설정되어 있지 않기 때문입니다.',
	'securepoll-dump-not-finished' => '암호화된 선거 기록은 오직 마지막 기한이 지난 뒤에야 이용하실 수 있습니다:$1',
	'securepoll-dump-no-urandom' => '/dev/urandom을 열 수 없습니다.
투표자의 사생활을 보호하기 위해서, 암호화된 선거 기록은 안전한 무작위 숫자 흐름으로 뒤섞일 수 있을 때 오직 공적으로 이용 가능합니다.',
	'securepoll-translate-title' => '번역: $1',
	'securepoll-invalid-language' => '"$1"은 인식되지 않는 언어 코드입니다.',
	'securepoll-submit-translate' => '갱신',
	'securepoll-language-label' => '선택한 언어:',
	'securepoll-submit-select-lang' => '번역',
);

/** Ripoarisch (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'securepoll' => 'Sescher Afshtemme',
	'securepoll-desc' => 'E Zohsatz-Projramm för Wahle, Meinunge, un Afstemmunge.',
	'securepoll-invalid-page' => '„<nowiki>$1</nowiki>“ es en onjöltijje Ongersigg',
	'securepoll-too-few-params' => 'Dä Lengk es verkeht, et sin nit jenooch Parrameetere för Ongersigge do dren.',
	'securepoll-invalid-election' => '„$1“ es kein jöltije Kennung för en Afshtemmung',
	'securepoll-not-authorised' => 'Do häs nit et Rääsch, bei hee dä Afshtemmung met_ze_maache.',
	'securepoll-welcome' => '<strong>Hallo $1,</strong>',
	'securepoll-not-started' => 'Hee di Afshtemmung hät noch jaa nit aanjefange.
Et sull aam $1 loß jonn.',
	'securepoll-not-qualified' => 'Do brengks nit alles met, wat nüdesch es, öm bei hee dä Afshtemmung met_ze_maache: $1',
	'securepoll-change-disallowed' => 'Do häs ald afjeshtemmpt.
Noch ens Afshtemme es nit müjjelesch.',
	'securepoll-change-allowed' => '<strong>Opjepaß: Do häs zo däm Teema ald afjeshtemmp.</strong>
Ding Shtemm kanns De ändere. Donn doför tat Fommullaa hee drunge namme. Wann De dat määs, weet Ding vörher afjejovve Shtemm fott jeschmeße.',
	'securepoll-submit' => 'De Shtemm afjävve',
	'securepoll-gpg-receipt' => 'Häs Dangk för et Afshtemme.

Wann De wells donn Der dat hee als en Quittung för Ding Shtemm faßhaale:

<pre>$1</pre>',
	'securepoll-thanks' => 'Mer donn uns bedangke. Ding Shtemm es faßjehallde.',
	'securepoll-return' => 'Jangk retuur noh $1',
	'securepoll-encrypt-error' => 'Kunnt Ding Shtemm nit verschlößele.
Ding Shtemm es nit jezallt, un weed nit faßjehallde!

$1',
	'securepoll-no-gpg-home' => 'Kann dat Verzeichnis för GPG nit aanlääje.',
	'securepoll-secret-gpg-error' => 'Ene Fähler es opjetrodde bem Ußföhre vun GPG.
Donn <code>$wgSecurePollShowErrorDetail=true;</code>
en <code>LocalSettings.php</code>
endraare, öm mieh Einzelheite ze sinn ze krijje.',
	'securepoll-full-gpg-error' => 'Ene Fähler es opjetrodde bem Ußföhre vun GPG:

Kommando: $1

Fähler:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'De Schlössel för GPG sen verkeeht enjeshtallt.',
	'securepoll-gpg-parse-error' => 'Ene Fähler es opjetrodde bemm Beärbeide vun dämm, wat GPG ußjejovve hät.',
	'securepoll-no-decryption-key' => 'Mer han keine Schlößel för et Äntschlößele, un et es och keine enjeshtallt. Alsu künne mer nix Äntschlößele.',
	'securepoll-list-title' => 'Shtemme Opleßte: $1',
	'securepoll-header-timestamp' => 'Zick',
	'securepoll-header-user-name' => 'Name',
	'securepoll-header-user-domain' => 'Domähn',
	'securepoll-header-ua' => 'Däm Metmaacher singe Brauser',
	'securepoll-header-strike' => 'Ußjeschtresche?',
	'securepoll-header-details' => 'Einzelheite',
	'securepoll-strike-button' => 'Ußshtriische',
	'securepoll-unstrike-button' => 'nit mieh jeschtresche',
	'securepoll-strike-reason' => 'Aaanlaß o Jrund:',
	'securepoll-strike-cancel' => 'Ophüre!',
	'securepoll-strike-error' => 'Ene Fähler is opjetrodde beim Ußshtriishe odder widder zerök holle: $1',
	'securepoll-need-admin' => 'Do moß ene {{int:group-sysadmin-member}} sin, öm dat maache ze dörve.',
	'securepoll-details-link' => 'Einzelheite',
	'securepoll-details-title' => 'Einzelheite vun dä Shtemm: #$1',
	'securepoll-invalid-vote' => '„$1“ en en onjöltijje Kännong för en Shtemm',
	'securepoll-header-user-type' => 'Metmaacher-Zoot',
	'securepoll-voter-properties' => 'Dem Metmaacher sing Eijeschaffte för et Afshtemme',
	'securepoll-strike-log' => 'Logboch övver de ußjeshtersche un widder jehollte Shtemme en Afshtemmunge',
	'securepoll-header-action' => 'Akßjuhn',
	'securepoll-header-reason' => 'Woröm?',
	'securepoll-header-admin' => '{{int:group-sysop-member}}',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'securepoll' => 'Securiséiert Ëmfro',
	'securepoll-desc' => 'Erweiderung fir Walen an Ëmfroen',
	'securepoll-invalid-page' => 'Net-valabel Ënnersäit "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Net genuch Ënnersäite-Parameter (net valbele Link).',
	'securepoll-not-authorised' => 'Dir sidd net berechtegt fir bäi dëse Wale mattzemaachen.',
	'securepoll-welcome' => '<strong>Wëllkomm $1!</strong>',
	'securepoll-not-started' => "D'Walen hunn nach net ugefaang.
Si fänke viraussiichtlech den $1 un.",
	'securepoll-not-qualified' => 'Dir sidd net qualifizéiert fir bäi dëse Walen ofzestëmmen: $1',
	'securepoll-change-disallowed' => 'Dir hutt bäi dëse Walen virdru schonn ofgestëmmt.
Pardon, mee dir däerft net nach eng Kéier ofstëmmen.',
	'securepoll-submit' => 'Stëmm ofginn',
	'securepoll-thanks' => 'Merci, Är Stëmm gouf gespäichert.',
	'securepoll-return' => 'Zréck op $1',
	'securepoll-encrypt-error' => 'Bei der Verschlëselung vun Ärer Stëmm ass a Feeler geschitt.
Är Stëmm gouf net gespäichert!

$1',
	'securepoll-gpg-parse-error' => 'Feeler beim Interpretéieren vum GPG-Ouput',
	'securepoll-no-decryption-key' => 'Et ass keen Ëntschlësungsschlëssel agestallt.
Ëntschlësselung onméiglech.',
	'securepoll-list-title' => 'Lëscht vun de Stëmmen: $1',
	'securepoll-header-timestamp' => 'Zäit',
	'securepoll-header-user-name' => 'Numm',
	'securepoll-header-strike' => 'Duerchsträichen',
	'securepoll-header-details' => 'Detailer',
	'securepoll-strike-button' => 'Duerchsträichen',
	'securepoll-unstrike-button' => 'Duerchsträichen ewechhuelen',
	'securepoll-strike-reason' => 'Grond:',
	'securepoll-strike-cancel' => 'Ofbriechen',
	'securepoll-need-admin' => 'Dir musst Admnistrateur si fir dëst kënnen ze maachen.',
	'securepoll-details-link' => 'Detailer',
	'securepoll-header-user-type' => 'Benotzertyp',
	'securepoll-voter-properties' => 'Eegeschafte vum Wieler',
	'securepoll-header-action' => 'Aktioun',
	'securepoll-header-reason' => 'Grond',
	'securepoll-header-admin' => 'Administrateur',
	'securepoll-translate-title' => 'Iwwersetzen: $1',
	'securepoll-invalid-language' => 'Net valabele Sproochecode "$1"',
	'securepoll-submit-translate' => 'Aktualiséieren',
	'securepoll-language-label' => 'Sprooch eraussichen:',
	'securepoll-submit-select-lang' => 'Iwwersetzen',
);

/** Limburgish (Limburgs)
 * @author Ooswesthoesbes
 */
$messages['li'] = array(
	'securepoll' => 'VeiligSjtömme',
	'securepoll-desc' => 'Oetbreiding veur verkeziginge en vraogelieste',
	'securepoll-invalid-page' => 'Óngeljige subpaasj "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Óngenóg subpaasjparamaeters (óngeljige verwiezing).',
	'securepoll-invalid-election' => '"$1" is gein geljig verkezigingsnómmer.',
	'securepoll-not-authorised' => 'Doe bös neet bevoog óm te sjtömme in dees sjtömming.',
	'securepoll-welcome' => '<strong>Wèlkóm $1!</strong>',
	'securepoll-not-started' => 'Dees sjtömming is nag neet gesjtart.
De sjtömming vank op $1 aan.',
	'securepoll-not-qualified' => 'Doe bös neet bevoog óm te sjtömme in dees sjtömming: $1',
	'securepoll-change-disallowed' => 'Doe höbs al gesjtömp in dees sjtömming.
Doe moogs neet opnuuj sjtömme.',
	'securepoll-change-allowed' => "<strong>Opmèrking: Doe höbs al gesjtömp in dees sjtömming.</strong>
Doe kins dien sjtöm wiezige door 't óngersjtäönde formeleer op te sjlaon.
As se daoveur keus, wörd diene edere sjtöm verwiederd.",
	'securepoll-submit' => 'Sjlaon sjtöm op',
	'securepoll-gpg-receipt' => 'Danke veur diene sjtöm.

Doe kins de óngersjtäönde gegaeves beware as bewies van dien deilnaam aan dees sjtömming;

<pre>$1</pre>',
	'securepoll-thanks' => 'Danke, dien sjtöm is óntvange en opgesjlage.',
	'securepoll-return' => 'trök nao $1',
	'securepoll-encrypt-error' => "'t Codere van dien sjtöm is misluk.
Dien sjtöm is neet opgesjlage!

$1",
	'securepoll-no-gpg-home' => "'t Waas neet meugelik óm de thoesmap veur GPG aan te make.",
	'securepoll-secret-gpg-error' => "d'r Is 'n fout opgetraoje bie 't oetveure van GPG.
Gebroek \$wgSecurePollShowErrorDetail=true; in LocalSettings.php óm meer details waer te gaeve.",
	'securepoll-full-gpg-error' => "d'r Is 'n fout opgetraoje bie 't oetveure van GPG:

Beveel: $1

Fotmeljing:
<pre>$2</pre>",
	'securepoll-gpg-config-error' => 'De GPG-sjleutels zeen ónjuus ingesjteld.',
	'securepoll-gpg-parse-error' => "d'r Is 'n fout opgetraoje bie 't interpretere van GPG-oetveur.",
	'securepoll-no-decryption-key' => "d'r Is geine decryptiesjleutel ingesjteld.
Decodere is neet meugelik.",
	'securepoll-list-title' => 'Sóm sjtömme op: $1',
	'securepoll-header-timestamp' => 'Tied',
	'securepoll-header-user-name' => 'Naom',
	'securepoll-header-user-domain' => 'Domien',
	'securepoll-header-ua' => 'Gebroekeragent',
	'securepoll-header-strike' => 'Haol door',
	'securepoll-header-details' => 'Details',
	'securepoll-strike-button' => 'Haol door',
	'securepoll-unstrike-button' => 'Haol neet door',
	'securepoll-strike-reason' => 'Raej:',
	'securepoll-strike-cancel' => 'Braek aaf',
	'securepoll-strike-error' => "Fout bie 't (neet) doorhaole: $1",
	'securepoll-need-admin' => "Doe mós 'ne beheerder zeen óm dees hanjeling te moge oetveure.",
	'securepoll-details-link' => 'Details',
	'securepoll-details-title' => 'Sjtömdetails: #$1',
	'securepoll-invalid-vote' => '"$1" is gein geljig sjtömnómmer',
	'securepoll-header-user-type' => 'Gebroekerstype',
	'securepoll-voter-properties' => 'Sjtömbeneudighede',
	'securepoll-strike-log' => 'Doorhaolingslogbook',
	'securepoll-header-action' => 'Hanjeling',
	'securepoll-header-reason' => 'Raej',
	'securepoll-header-admin' => 'Beheer',
	'securepoll-dump-title' => 'Dump: $1',
	'securepoll-translate-title' => 'Vertaol: $1',
	'securepoll-invalid-language' => 'Óngeljige taolcode "$1"',
	'securepoll-submit-translate' => 'Wèrk bie',
	'securepoll-language-label' => 'Selecteer taol:',
	'securepoll-submit-select-lang' => 'Vertaol',
);

/** Macedonian (Македонски)
 * @author Brest
 */
$messages['mk'] = array(
	'securepoll-header-timestamp' => 'Време',
	'securepoll-header-user-name' => 'Име',
	'securepoll-header-user-domain' => 'Домен',
	'securepoll-language-label' => 'Избери јазик:',
);

/** Malay (Bahasa Melayu)
 * @author Aurora
 */
$messages['ms'] = array(
	'securepoll-desc' => 'Sambungan untuk pemilihan dan tinjauan',
	'securepoll-invalid-page' => 'Sublaman tidak sah "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Parameter sublaman tidak cukup (pautan tidak sah).',
	'securepoll-invalid-election' => '"$1" bukan merupakan ID pemilihan yang sah.',
	'securepoll-not-authorised' => 'Anda tidak diizinkan mengundi di dalam pemilihan ini.',
	'securepoll-welcome' => '<strong>Selamat datang $1!</strong>',
	'securepoll-not-started' => 'Pemilihan ini belum lagi bermula.
Ia dijadualkan bermula pada $1.',
	'securepoll-not-qualified' => 'Anda tidak layak mengundi di dalam pemilihan ini: $1',
	'securepoll-change-disallowed' => 'Anda telah mengundi di dalam pemilihan ini sebelum ini.
Maaf, anda tidak boleh mengundi sekali lagi.',
	'securepoll-change-allowed' => '<strong>Nota: Anda telah mengundi di dalam pemilihan ini sebelum ini.</strong>
Anda boleh menukar undi anda dengan menyerahkan borang di bawah.
Perhatikan bahawa jika anda berbuat demikian, undi asal anda akan dimansuhkan.',
	'securepoll-submit' => 'Serah undian',
	'securepoll-gpg-receipt' => 'Terima kasih kerana mengundi.

Jika anda mahu, anda boleh menyimpan resit yang berikut sebagai bukti undian anda:

<pre>$1</pre>',
	'securepoll-thanks' => 'Terima kasih, undi anda telah direkodkan.',
	'securepoll-return' => 'Kembali ke $1',
	'securepoll-encrypt-error' => 'Gagal menyulitkan rekod undian anda.
Undi anda tidak direkodkan!

$1',
	'securepoll-no-gpg-home' => 'Tidak dapat mencipta direktori rumah GPG.',
	'securepoll-secret-gpg-error' => 'Ralat melakukan GPG.
Gunakan $wgSecurePollShowErrorDetail=true; dalam LocalSettings.php untuk menunjukkan butiran lebih.',
	'securepoll-full-gpg-error' => 'Ralat melakukan GPG:

Arahan: $1

Ralat:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'Kunci GPG tidak dibentuk dengan betul.',
	'securepoll-gpg-parse-error' => 'Ralat mentafsirkan output GPG.',
	'securepoll-no-decryption-key' => 'Tiada kunci penyahsulitan dibentuk.
Tidak dapat menyahsulit.',
);

/** Low German (Plattdüütsch)
 * @author Slomox
 */
$messages['nds'] = array(
	'securepoll' => 'SekerAfstimmen',
	'securepoll-desc' => 'Extension för Wahlen un Ümfragen',
	'securepoll-invalid-page' => 'Ungüllige Ünnersied „<nowiki>$1</nowiki>“',
	'securepoll-invalid-election' => '„$1“ is keen güllige Wahl-ID.',
	'securepoll-not-authorised' => 'Du dröffst bi disse Wahl nich mit afstimmen.',
	'securepoll-welcome' => '<strong>Willkamen $1!</strong>',
	'securepoll-not-qualified' => 'Du dröffst bi disse Wahl nich mit afstimmen: $1',
	'securepoll-submit' => 'Stimm afgeven',
	'securepoll-thanks' => 'Wees bedankt, dien Stimm is optekent.',
	'securepoll-return' => 'Trüch na $1',
);

/** Dutch (Nederlands)
 * @author Mwpnl
 * @author Siebrand
 */
$messages['nl'] = array(
	'securepoll' => 'VeiligStemmen',
	'securepoll-desc' => 'Uitbreiding voor verkiezingen en enquêtes',
	'securepoll-invalid-page' => 'Ongeldige subpagina "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Onvoldoende subpaginaparameters (ongeldige verwijzing).',
	'securepoll-invalid-election' => '"$1" is geen geldig verkiezingsnummer.',
	'securepoll-not-authorised' => 'U bent niet bevoegd om te stemmen in deze stemming.',
	'securepoll-welcome' => '<strong>Welkom $1!</strong>',
	'securepoll-not-started' => 'Deze stemming is nog niet gestart.
De stemming begint op $1.',
	'securepoll-not-qualified' => 'U bent niet bevoegd om te stemmen in deze stemming: $1',
	'securepoll-change-disallowed' => 'U hebt al gestemd in deze stemming.
U mag niet opnieuw stemmen.',
	'securepoll-change-allowed' => '<strong>Opmerking: U hebt al gestemd in deze stemming.</strong>
U kunt uw stem wijzigigen door het onderstaande formulier op te slaan.
Als u daarvoor kiest, wordt uw eerdere stem verwijderd.',
	'securepoll-submit' => 'Stem opslaan',
	'securepoll-gpg-receipt' => 'Dank u voor uw stem.

U kunt de onderstaande gegevens bewaren als bewijs van uw deelname aan deze stemming:

<pre>$1</pre>',
	'securepoll-thanks' => 'Dank u wel. Uw stem is ontvangen en opgeslagen.',
	'securepoll-return' => 'terug naar $1',
	'securepoll-encrypt-error' => 'Het coderen van uw stem is mislukt.
Uw stem is niet opgeslagen!

$1',
	'securepoll-no-gpg-home' => 'Het was niet mogelijk om de thuismap voor GPG aan te maken.',
	'securepoll-secret-gpg-error' => 'Er is een fout opgetreden bij het uitvoeren van GPG.
Gebruik $wgSecurePollShowErrorDetail=true; in LocalSettings.php om meer details weer te geven.',
	'securepoll-full-gpg-error' => 'Er is een fout opgetreden bij het uitvoeren van GPG:

Commando: $1

Foutmelding:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'De GPG-sleutels zijn onjuist ingesteld.',
	'securepoll-gpg-parse-error' => 'Er is een fout opgetreden bij het interpreteren van GPG-uitvoer.',
	'securepoll-no-decryption-key' => 'Er is geen decryptiesleutel ingesteld.
Decoderen is niet mogelijk.',
	'securepoll-header-timestamp' => 'Tijd',
	'securepoll-header-user-name' => 'Naam',
	'securepoll-header-user-domain' => 'Domein',
	'securepoll-header-ua' => 'User-agent',
	'securepoll-header-strike' => 'Doorhalen',
	'securepoll-header-details' => 'Details',
	'securepoll-strike-button' => 'Doorhalen',
	'securepoll-unstrike-button' => 'Doorhalen ongedaan maken',
	'securepoll-strike-reason' => 'Reden:',
	'securepoll-strike-cancel' => 'Annuleren',
	'securepoll-need-admin' => 'U moet een beheerder zijn om deze handeling te mogen uitvoeren.',
	'securepoll-details-link' => 'Details',
	'securepoll-invalid-vote' => '"$1" is geen geldig stemnummer',
	'securepoll-header-user-type' => 'Gebruikerstype',
	'securepoll-header-action' => 'Handeling',
	'securepoll-header-reason' => 'Reden',
	'securepoll-header-admin' => 'Beheer',
	'securepoll-submit-translate' => 'Bijwerken',
	'securepoll-language-label' => 'Taal selecteren:',
	'securepoll-submit-select-lang' => 'Vertalen',
);

/** Norwegian Nynorsk (‪Norsk (nynorsk)‬)
 * @author Eirik
 * @author Harald Khan
 */
$messages['nn'] = array(
	'securepoll-desc' => 'Ei utviding for val og undersøkingar',
	'securepoll-invalid-page' => 'Ugyldig underside «<nowiki>$1</nowiki>»',
	'securepoll-too-few-params' => 'Ikkje nok undersideparametrar (ugyldig lenkje)',
	'securepoll-invalid-election' => '«$1» er ikkje ein gyldig val-ID.',
	'securepoll-not-authorised' => 'Du har ikkje høve til å røysta i dette valet.',
	'securepoll-welcome' => '<strong>Velkomen, $1!</strong>',
	'securepoll-not-started' => 'Dette valet har ikkje starta enno.
Det gjer det etter planen $1.',
	'securepoll-not-qualified' => 'Du er ikkje kvalifisert til å røysta i dette valet: $1',
	'securepoll-change-disallowed' => 'Du har alt røysta i dette valet og kan ikkje røysta på nytt.',
	'securepoll-change-allowed' => '<strong>Merk: Du har alt røysta i dette valet.</strong>
Du kan endra røysta di gjennom å senda inn skjemaet under.
Merk at om du gjer dette, vil den opphavlege røysta di verta sletta.',
	'securepoll-submit' => 'Røyst',
	'securepoll-gpg-receipt' => 'Takk for at du gav røysta di.

Om du ynskjer det, kan du ta vare på den fylgjande kvitteringa som eit prov på røysta di:

<pre>$1</pre>',
	'securepoll-thanks' => 'Takk, røysta di er vorten registrert.',
	'securepoll-return' => 'Attende til $1',
	'securepoll-encrypt-error' => 'Klarte ikkje kryptera røysta di.
Ho har ikkje vorte registrert!

$1',
	'securepoll-secret-gpg-error' => 'Feil ved køyring av GPG.
Bruk $wgSecurePollShowErrorDetail=true; i LocalSettings.php for å sjå fleire detaljar.',
	'securepoll-full-gpg-error' => 'Feil ved køyring av GPG:

Kommando: $1

Feil:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG-nøklane er ikkje sette opp rett.',
	'securepoll-gpg-parse-error' => 'Feil ved tolking av utdata frå GPG.',
	'securepoll-no-decryption-key' => 'Ingen dekrypteringsnøkkel er sett opp.
Kan ikkje dekryptera.',
);

/** Norwegian (bokmål)‬ (‪Norsk (bokmål)‬)
 * @author Finnrind
 * @author Stigmj
 */
$messages['no'] = array(
	'securepoll' => 'SikkertValg',
	'securepoll-desc' => 'En utvidelse for valg og undersøkelser',
	'securepoll-invalid-page' => 'Ugyldig underside «<nowiki>$1</nowiki>»',
	'securepoll-too-few-params' => 'Ikke mange nok undersideparametre (ugyldig lenke).',
	'securepoll-invalid-election' => '"$1" er ikke en gyldig valg-id.',
	'securepoll-not-authorised' => 'Du har ikke anledning til å stemme i dette valget.',
	'securepoll-welcome' => '<strong>Velkommen $1!</strong>',
	'securepoll-not-started' => 'Dette valget har enda ikke startet.
Det starter etter planen $1.',
	'securepoll-not-qualified' => 'Du er ikke kvalifisert til å stemme i dette valget: $1',
	'securepoll-change-disallowed' => 'Du har allerede stemt i dette valget.
Du kan desverre ikke stemme på nytt.',
	'securepoll-change-allowed' => '<strong>Bemerk: Du har allerede stemt i dette valget.</strong>
Du kan endre stemmen din ved å sende inn skjemaet nedenfor.
Bemerk at dersom du gjør dette vil den opprinnelige stemmen din bli forkastet.',
	'securepoll-submit' => 'Avgi stemme',
	'securepoll-gpg-receipt' => 'Takk for at du avga stemme.

Dersom du ønsker det kan du ta vare på følgende kvittering som bevis på din stemme:

<pre>$1</pre>',
	'securepoll-thanks' => 'Takk, stemmen din har blitt registrert.',
	'securepoll-return' => 'Tilbake til $1',
	'securepoll-encrypt-error' => 'Klarte ikke å kryptere din stemme.
Stemmen har ikke blitt registrert!

$1',
	'securepoll-no-gpg-home' => 'Kunne ikke opprette hjemmekatalog for GPG',
	'securepoll-secret-gpg-error' => 'Feil ved kjøring av GPG.
bruk $wgSecurePollShowErrorDetail=true; i LocalSettings.php for å se flere detaljer.',
	'securepoll-full-gpg-error' => 'Feil under kjøring av GPG:

Kommando: $1

Feil:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG-nøklene er ikke satt opp riktig.',
	'securepoll-gpg-parse-error' => 'Feil under tolking av utdata fra GPG.',
);

/** Occitan (Occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'securepoll' => 'Sondatge securizat',
	'securepoll-desc' => "Extension per d'eleccions e de sondatges",
	'securepoll-invalid-page' => 'Sospagina « <nowiki>$1</nowiki> » invalida',
	'securepoll-too-few-params' => 'Pas pro de paramètres de sospagina (ligam invalid).',
	'securepoll-invalid-election' => "« $1 » es pas un identificant d'eleccion valid.",
	'securepoll-not-authorised' => 'Sètz pas autorizat(ada) a votar per aquesta eleccion.',
	'securepoll-welcome' => '<strong>Benvenguda $1 !</strong>',
	'securepoll-not-started' => "L'eleccion a pas encara començat.
Començarà lo $1.",
	'securepoll-not-qualified' => 'Sètz pas qualificat(ada) per votar dins aquesta eleccion : $1',
	'securepoll-change-disallowed' => 'Ja avètz votat per aquesta eleccion.
O planhèm, podètz pas votar tornamai.',
	'securepoll-change-allowed' => '<strong>Nòta : Ja avètz votat per aquesta eleccion.</strong>
Podètz cambiar vòstre vòte en sometent lo formulari çaijós.
Se fasètz aquò, vòstre vòte ancian serà anullat.',
	'securepoll-submit' => 'Sometre lo vòte',
	'securepoll-gpg-receipt' => "Mercés per vòstre vòte.

S'o desiratz, podètz gardar aquò coma pròva de vòstre vòte :

<pre>$1</pre>",
	'securepoll-thanks' => 'Mercés, vòstre vòte es estat enregistrat.',
	'securepoll-return' => 'Tornar a $1',
	'securepoll-encrypt-error' => 'Lo criptatge de vòstre vòte a fracassat.
Vòstre vòte es pas estat enregistrat !

$1',
	'securepoll-no-gpg-home' => 'Impossible de crear lo dorsièr de basa de GPG.',
	'securepoll-secret-gpg-error' => 'Error al moment de l\'execucion de GPG.
Apondètz $wgSecurePollShowErrorDetail=true; a LocalSettings.php per afichar mai de detalhs.',
	'securepoll-full-gpg-error' => "Error al moment de l'execucion de GPG :

Comanda : $1

Error :
<pre>$2</pre>",
	'securepoll-gpg-config-error' => 'Las claus de GPG son pas configuradas corrèctament.',
	'securepoll-gpg-parse-error' => "Error al moment de l'interpretacion de la sortida de GPG.",
	'securepoll-no-decryption-key' => 'Cap de clau de descriptatge es pas estada configurada.
Impossible de descriptar.',
	'securepoll-list-title' => 'Lista dels vòtes : $1',
	'securepoll-header-timestamp' => 'Ora',
	'securepoll-header-user-name' => 'Nom',
	'securepoll-header-user-domain' => 'Domeni',
	'securepoll-header-ua' => "Agent d'utilizaire",
	'securepoll-header-strike' => 'Raiar',
	'securepoll-header-details' => 'Detalhs',
	'securepoll-strike-button' => 'Raiar',
	'securepoll-unstrike-button' => 'Desraiar',
	'securepoll-strike-reason' => 'Rason :',
	'securepoll-strike-cancel' => 'Anullar',
	'securepoll-strike-error' => 'Error al moment del (des)raiatge : $1',
	'securepoll-need-admin' => 'Vos cal èsser un administrator per executar aquesta accion.',
	'securepoll-details-link' => 'Detalhs',
	'securepoll-details-title' => 'Detalhs del vòte : #$1',
	'securepoll-invalid-vote' => '« $1 » es pas un ID de vòte valid',
	'securepoll-header-user-type' => "Tipe de l'utilizaire",
	'securepoll-voter-properties' => 'Proprietats del votant',
	'securepoll-strike-log' => 'Jornal dels raiatges',
	'securepoll-header-action' => 'Accion',
	'securepoll-header-reason' => 'Rason',
	'securepoll-header-admin' => 'Administrator',
	'securepoll-dump-title' => 'Extrach : $1',
	'securepoll-dump-no-crypt' => 'Las donadas chifradas son pas disponiblas per aquesta eleccion, perque l’eleccion es pas configurada per utilizar un chiframent.',
	'securepoll-dump-not-finished' => "Las donadas criptadas son disponiblas soalment aprèp la clausura de l'eleccion : $1",
	'securepoll-dump-no-urandom' => 'Impossible de dobrir /dev/urandom.
Per manténer la confidencialitat dels votants, las donadas criptadas son disponiblas sonque se pòdon èsser reboladas amb un nombre de caractèrs aleatòris.',
	'securepoll-translate-title' => 'Traduire : $1',
	'securepoll-invalid-language' => 'Còde de lenga « $1 » invalid.',
	'securepoll-submit-translate' => 'Metre a jorn',
	'securepoll-language-label' => 'Seleccionar la lenga :',
	'securepoll-submit-select-lang' => 'Traduire',
);

/** Polish (Polski)
 * @author Sp5uhe
 */
$messages['pl'] = array(
	'securepoll' => 'Głosowanie',
	'securepoll-desc' => 'Rozszerzenie do realizacji wyborów oraz sondaży',
	'securepoll-invalid-page' => 'Nieprawidłowa podstrona „<nowiki>$1</nowiki>”',
	'securepoll-too-few-params' => 'Niewystarczające parametry podstrony (nieprawidłowy link).',
	'securepoll-invalid-election' => '„$1” nie jest prawidłowym identyfikatorem głosowania.',
	'securepoll-not-authorised' => 'Nie jesteś uprawniony do głosowania w tych wyborach.',
	'securepoll-welcome' => '<strong>Witamy Cię $1!</strong>',
	'securepoll-not-started' => 'Wybory jeszcze się nie rozpoczęły.
Planowane rozpoczęcie $1.',
	'securepoll-not-qualified' => 'Nie jesteś upoważniony do głosowania w wyborach $1',
	'securepoll-change-disallowed' => 'W tych wyborach już głosowałeś.
Nie możesz ponownie zagłosować.',
	'securepoll-change-allowed' => '<strong>Uwaga – głosowałeś już w tych wyborach.</strong>
Możesz zmienić swój głos poprzez zapisanie poniższego formularza.
Jeśli to zrobisz, Twój oryginalny głos zostanie anulowany.',
	'securepoll-submit' => 'Zapisz głos',
	'securepoll-gpg-receipt' => 'Dziękujemy za oddanie głosu.

Jeśli chcesz, możesz zachować poniższe pokwitowanie jako dowód.

<pre>$1</pre>',
	'securepoll-thanks' => 'Twój głos został zarejestrowany.',
	'securepoll-return' => 'Wróć do $1',
	'securepoll-encrypt-error' => 'Nie można zaszyfrować rekordu głosowania.
Twój głos nie został zarejestrowany! 

$1',
	'securepoll-no-gpg-home' => 'Nie można utworzyć katalogu domowego GPG.',
	'securepoll-secret-gpg-error' => 'Błąd podczas uruchamiania GPG.
Ustaw $wgSecurePollShowErrorDetail=true; w LocalSettings.php aby zobaczyć szczegóły.',
	'securepoll-full-gpg-error' => 'Błąd podczas uruchamiania GPG:

Polecenie – $1

Błąd:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'Klucze GPG zostały nieprawidłowo skonfigurowane.',
	'securepoll-gpg-parse-error' => 'Błąd interpretacji wyników GPG.',
	'securepoll-no-decryption-key' => 'Klucz odszyfrowujący nie został skonfigurowany.
Odszyfrowanie nie jest możliwe.',
	'securepoll-list-title' => 'Lista głosów – $1',
	'securepoll-header-timestamp' => 'Czas',
	'securepoll-header-user-name' => 'Nazwa',
	'securepoll-header-user-domain' => 'Domena',
	'securepoll-header-ua' => 'Aplikacja klienta',
	'securepoll-header-strike' => 'Skreślony',
	'securepoll-header-details' => 'Szczegóły',
	'securepoll-strike-button' => 'Skreśl',
	'securepoll-unstrike-button' => 'Usuń skreślenie',
	'securepoll-strike-reason' => 'Powód',
	'securepoll-strike-cancel' => 'Zrezygnuj',
	'securepoll-strike-error' => 'Błąd podczas skreślania lub usuwania skreślenia – $1',
	'securepoll-need-admin' => 'Musisz być administratorem, aby wykonać tę operację.',
	'securepoll-details-link' => 'Szczegóły',
	'securepoll-details-title' => 'Szczegóły głosu nr $1',
	'securepoll-invalid-vote' => '„$1” nie jest poprawnym identyfikatorem głosu',
	'securepoll-header-user-type' => 'Typ użytkownika',
	'securepoll-voter-properties' => 'Dane wyborcy',
	'securepoll-strike-log' => 'Rejestr skreślania',
	'securepoll-header-action' => 'Czynność',
	'securepoll-header-reason' => 'Powód',
	'securepoll-header-admin' => 'Administrator',
);

/** Portuguese (Português)
 * @author Malafaya
 * @author Waldir
 */
$messages['pt'] = array(
	'securepoll' => 'Sondagem Segura',
	'securepoll-desc' => 'Extensão para eleições e sondagens',
	'securepoll-invalid-page' => 'Subpágina inválida: "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Parâmetros de subpágina insuficientes (ligação inválida).',
	'securepoll-invalid-election' => '"$1" não é um identificador de eleição válido.',
	'securepoll-not-authorised' => 'Você não está autorizado a votar nesta eleição.',
	'securepoll-welcome' => '<strong>Bem-vindo $1!</strong>',
	'securepoll-not-started' => 'Esta eleição ainda não se iniciou.
Está programada para começar em $1.',
	'securepoll-not-qualified' => 'Você não está qualificado a votar nesta eleição: $1',
	'securepoll-change-disallowed' => 'Você já votou nesta eleição antes.
Desculpe, você não pode votar novamente.',
	'securepoll-change-allowed' => '<strong>Nota: Você já votou nesta eleição antes.</strong>
Você pode mudar o seu voto, enviando o formulário abaixo.
Note que se você fizer isso, o seu voto original será removido.',
	'securepoll-submit' => 'Enviar voto',
	'securepoll-gpg-receipt' => 'Obrigado pelo seu voto.

Se desejar, você pode guardar o seguinte recibo como prova do seu voto:

<pre>$1</pre>',
	'securepoll-thanks' => 'Obrigado, o seu voto foi registado.',
	'securepoll-return' => 'Voltar para $1',
	'securepoll-encrypt-error' => 'Falha ao codificar o registo do seu voto.
O seu voto não foi registado!

$1',
	'securepoll-no-gpg-home' => 'Não foi possível criar diretório GPG de base.',
	'securepoll-secret-gpg-error' => 'Erro ao executar GPG.
Use $wgSecurePollShowErrorDetail=true; em LocalSettings.php para mostrar mais detalhes.',
	'securepoll-full-gpg-error' => 'Erro ao executar GPG:

Comando: $1

Erro:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'As chaves GPG estão mal configuradas.',
	'securepoll-gpg-parse-error' => 'Erro ao interpretar a saída GPG.',
	'securepoll-no-decryption-key' => 'Nenhuma chave de descodificação está configurada.
Não é possível descodificar.',
	'securepoll-list-title' => 'Listar votos: $1',
	'securepoll-header-timestamp' => 'Hora',
	'securepoll-header-user-name' => 'Nome',
	'securepoll-header-user-domain' => 'Domínio',
	'securepoll-strike-reason' => 'Motivo:',
	'securepoll-strike-cancel' => 'Cancelar',
	'securepoll-header-user-type' => 'Tipo de utilizador',
	'securepoll-header-reason' => 'Motivo',
	'securepoll-submit-select-lang' => 'Traduzir',
);

/** Brazilian Portuguese (Português do Brasil)
 * @author Eduardo.mps
 */
$messages['pt-br'] = array(
	'securepoll' => 'SecurePoll',
	'securepoll-desc' => 'Extensão para eleições e pesquisas',
	'securepoll-invalid-page' => 'Subpágina inválida "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Sem parâmetros de subpágina suficientes (ligação inválida).',
	'securepoll-invalid-election' => '"$1" não é um ID de eleição válido.',
	'securepoll-not-authorised' => 'Você não está autorizado a votar nesta eleição.',
	'securepoll-welcome' => '<strong>Bem vindo(a) $1!</strong>',
	'securepoll-not-started' => 'Esta eleição ainda não começou.
Ela está programada para se iniciar às $1.',
	'securepoll-not-qualified' => 'Você não está qualificado(a) para votar nesta eleição: $1',
	'securepoll-change-disallowed' => 'Você já votou nesta eleição previamente.
Desculpe, mas você não pode votar novamente.',
	'securepoll-change-allowed' => '<strong>Nota: Você já votou nesta eleição anteriormente.</strong>
Você pode mudar seu voto enviando o formulário abaixo.
Note que se fizer isso, seu voto original será descartado.',
	'securepoll-submit' => 'Enviar voto',
	'securepoll-gpg-receipt' => 'Obrigado por votar.

Se desejar, você pode ter o seguinte recibo como evidência do seu voto:

<pre>$1</pre>',
	'securepoll-thanks' => 'Obrigado, seu voto foi registrado.',
	'securepoll-return' => 'Retornar a $1',
	'securepoll-encrypt-error' => 'Falha ao criptografar seu registro de voto.
Seu voto não foi registrado!

$1',
	'securepoll-no-gpg-home' => 'Não foi possível criar o diretório raiz do GPG',
	'securepoll-secret-gpg-error' => 'Erro ao executar o GPG.
Utilize $wgSecurePollShowErrorDetail=true; no LocalSettings.php para exibir mais detalhes.',
	'securepoll-full-gpg-error' => 'Erro ao executar GPG:

Comando: $1

Erro:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'As chaves GPG estão configuradas incorretamente.',
	'securepoll-gpg-parse-error' => 'Erro ao interpretar os dados de saída do GPG.',
	'securepoll-no-decryption-key' => 'Nenhuma chave de descriptografia está configurada.
Não foi possível descriptografar.',
);

/** Russian (Русский)
 * @author Александр Сигачёв
 */
$messages['ru'] = array(
	'securepoll' => 'БезопасноеГолосование',
	'securepoll-desc' => 'Расширение для проведения выборов и опросов',
	'securepoll-invalid-page' => 'Ошибочная подстраница «<nowiki>$1</nowiki>»',
	'securepoll-too-few-params' => 'Не хватает параметров подстраницы (ошибочная ссылка).',
	'securepoll-invalid-election' => '«$1» не является допустимым идентификатором выборов.',
	'securepoll-not-authorised' => 'Вы не имеете полномочий голосовать на этих выборах.',
	'securepoll-welcome' => '<strong>Добро пожаловать, $1!</strong>',
	'securepoll-not-started' => 'Эти выборы ещё не начались.
Начало запланировано на $1.',
	'securepoll-not-qualified' => 'Вы не правомочны голосовать на этих выборах: $1',
	'securepoll-change-disallowed' => 'Вы уже голосовали на этих выборах ранее.
Извините, вы не можете проголосовать ещё раз.',
	'securepoll-change-allowed' => '<strong>Примечание. Вы уже голосовали на этих выборах ранее.</strong>
Вы можете изменить свой голос, отправив приведённую ниже форму.
Если вы сделаете это, то ваш предыдущий голос не будет учтён.',
	'securepoll-submit' => 'Отправить голос',
	'securepoll-gpg-receipt' => 'Благодарим за участие в голосовании.

При желании вы можете сохранить следующие строки как подтверждение вашего голоса:

<pre>$1</pre>',
	'securepoll-thanks' => 'Спасибо, ваш голос записан.',
	'securepoll-return' => 'Вернуться к $1',
	'securepoll-encrypt-error' => 'Не удалось зашифровать запись о вашем голосе.
Ваш голос не был записан!

$1',
	'securepoll-no-gpg-home' => 'Невозможно создать домашний каталог GPG.',
	'securepoll-secret-gpg-error' => 'Ошибка при выполнении GPG.
Задайте настройку $wgSecurePollShowErrorDetail=true; в файле LocalSettings.php чтобы получить более подробное сообщение.',
	'securepoll-full-gpg-error' => 'Ошибка при выполнении GPG:

Команда: $1

Ошибка:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG-ключи настроены неправильно.',
	'securepoll-gpg-parse-error' => 'Ошибка при интерпретации вывода GPG.',
	'securepoll-no-decryption-key' => 'Не настроен ключ расшифровки.
Невозможно  расшифровать.',
	'securepoll-list-title' => 'Список голосов: $1',
	'securepoll-header-timestamp' => 'Время',
	'securepoll-header-user-name' => 'Имя',
	'securepoll-header-user-domain' => 'Домен',
	'securepoll-header-ua' => 'Агент пользователя',
	'securepoll-header-strike' => 'Вычёркивание',
	'securepoll-header-details' => 'Подробности',
	'securepoll-strike-button' => 'Вычеркнуть',
	'securepoll-unstrike-button' => 'Снять вычёркивание',
	'securepoll-strike-reason' => 'Причина:',
	'securepoll-strike-cancel' => 'Отмена',
	'securepoll-strike-error' => 'Ошибка при вычёркивании или снятии вычёркивания: $1',
	'securepoll-need-admin' => 'Вы должны быть администратором, чтобы выполнить это действие.',
	'securepoll-details-link' => 'Подробности',
	'securepoll-details-title' => 'Подробности голосования: #$1',
	'securepoll-invalid-vote' => '«$1» не является допустимым идентификатором голосования',
	'securepoll-header-user-type' => 'Тип пользователя',
	'securepoll-voter-properties' => 'Свойства изберателя',
	'securepoll-strike-log' => 'Журнал вычёркиваний',
	'securepoll-header-action' => 'Действие',
	'securepoll-header-reason' => 'Причина',
	'securepoll-header-admin' => 'Админ',
);

/** Slovak (Slovenčina)
 * @author Helix84
 */
$messages['sk'] = array(
	'securepoll' => 'Zabezpečené hlasovanie',
	'securepoll-desc' => 'Rozšírenie pre voľby a dotazníky',
	'securepoll-invalid-page' => 'Neplatná podstránka „<nowiki>$1</nowiki>“',
	'securepoll-too-few-params' => 'Nedostatok parametrov podstránky (neplatný odkaz).',
	'securepoll-invalid-election' => '„$1“ nie je platný ID hlasovania.',
	'securepoll-not-authorised' => 'Nemáte oprávnenie hlasovať v tomto hlasovaní.',
	'securepoll-welcome' => '<strong>Vitajte $1!</strong>',
	'securepoll-not-started' => 'Tieto voľby zatiaľ nezačali.
Začiatok je naplánovaný na $1.',
	'securepoll-not-qualified' => 'Nekvalifikujete sa do tohto hlasovania: $1',
	'securepoll-change-disallowed' => 'V tomto hlasovaní ste už hlasovali.
Je mi ľúto, nemôžete znova voliť.',
	'securepoll-change-allowed' => '<strong>Pozn.: V tomto hlasovaní ste už hlasovali.</strong>
Svoj hlas môžete zmeniť zaslaním dolu uvedeného formulára.
Ak tak spravíte, váš pôvodný hlas sa zahodí.',
	'securepoll-submit' => 'Poslať hlas',
	'securepoll-gpg-receipt' => 'Ďakujeme za hlasovanie.

Ak chcete, môžete si ponechať nasledovné potvrdenie ako dôkaz o tom, že ste hlasovali:

<pre>$1</pre>',
	'securepoll-thanks' => 'Ďakujeme, váš hlas bol zaznamenaný.',
	'securepoll-return' => 'Späť na $1',
	'securepoll-encrypt-error' => 'Nepodarilo sa zašifrovať záznam o vašom hlasovaní.
Váš hlas nebol zaznamenaný!

$1',
	'securepoll-no-gpg-home' => 'Chyba pri vytváraní domovského adresára GPG.',
	'securepoll-secret-gpg-error' => 'Chyba pri spúšťaní GPG.
Ďalšie podrobnosti zobrazíte nastavením $wgSecurePollShowErrorDetail=true; v súbore LocalSettings.php.',
	'securepoll-full-gpg-error' => 'Chyba pri súpšťaní GPG:

Príkaz: $1

Chyba:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG kľúče sú nesprávne nakonfigurované.',
	'securepoll-gpg-parse-error' => 'Chyba pri interpretácii výstupu GPG.',
	'securepoll-no-decryption-key' => 'Nie je nakonfigurovaný žiadny dešifrovací kľúč.
Nie je možné dešifrovať.',
	'securepoll-list-title' => 'Zoznam hlasov: $1',
	'securepoll-header-timestamp' => 'Čas',
	'securepoll-header-user-name' => 'Meno',
	'securepoll-header-user-domain' => 'Doména',
	'securepoll-header-ua' => 'Prehliadač',
	'securepoll-header-strike' => 'Škrtnúť',
	'securepoll-header-details' => 'Podrobnosti',
	'securepoll-strike-button' => 'Škrtnúť',
	'securepoll-unstrike-button' => 'Zrušiť škrtnutie',
	'securepoll-strike-reason' => 'Dôvod:',
	'securepoll-strike-cancel' => 'Zrušiť',
	'securepoll-strike-error' => 'Chyba operácie škrtnutie/zrušenie škrtnutia: $1',
	'securepoll-need-admin' => 'Aby ste mohli vykonať túto operáciu, musíte byť správca.',
	'securepoll-details-link' => 'Podrobnosti',
	'securepoll-details-title' => 'Podrobnosti hlasovania: #$1',
	'securepoll-invalid-vote' => '„$1“ nie je platný ID hlasovania',
	'securepoll-header-user-type' => 'Typ používateľa',
	'securepoll-voter-properties' => 'Vlastnosti hlasujúceho',
	'securepoll-strike-log' => 'Záznam škrtnutí',
	'securepoll-header-action' => 'Operácia',
	'securepoll-header-reason' => 'Dôvod',
	'securepoll-header-admin' => 'Správca',
);

/** Swedish (Svenska)
 * @author Najami
 */
$messages['sv'] = array(
	'securepoll' => 'SäkerOmröstning',
	'securepoll-desc' => 'Ett programtillägg för omröstningar och enkäter',
	'securepoll-invalid-page' => 'Ogiltig undersida "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Ej tillräckligt många undersideparametrar (ogiltig länk).',
	'securepoll-invalid-election' => '"$1" är inte ett giltigt omröstnings-ID.',
	'securepoll-not-authorised' => 'Du är inte bemyndigad att rösta i den här omröstningen.',
	'securepoll-welcome' => '<strong>Välkommen $1!</strong>',
	'securepoll-not-started' => 'Den här omröstningen är inte startat ännu.
Den planeras starta den $1.',
	'securepoll-not-qualified' => 'Du är inte kvalificerad att rösta i den här omröstningen: $1',
	'securepoll-change-disallowed' => 'Du har redan röstat i den här omröstningen.
Du kan tyvärr inte rösta igen.',
	'securepoll-change-allowed' => '<strong>Observera att du redan har röstat i den här omröstningen.</strong>
Du kan ändra din röst genom att skicka in formuläret nedan.
Observera att om du gör det här, kommer din ursprungliga röst att slängas.',
	'securepoll-submit' => 'Spara röst',
	'securepoll-gpg-receipt' => 'Tack för din röst.

Om du vill kan du behålla följande kvitto som ett bevis på din röst:

<pre>$1</pre>',
	'securepoll-thanks' => 'Tack, din röst har registrerats.',
	'securepoll-return' => 'Tillbaka till $1',
	'securepoll-encrypt-error' => 'Misslyckades att kryptera din röst.
Din röst har inte registrerats!

$1',
	'securepoll-no-gpg-home' => 'Kunde inte skapa en GPG-hemkatalog.',
	'securepoll-secret-gpg-error' => 'Ett fel uppstod när GPG exekverades.
Använd $wgSecurePollShowErrorDetail=true; i LocalSettings.php för mer information.',
	'securepoll-full-gpg-error' => 'Ett fel uppstod när GPG exekverades:

Kommando: $1

Fel:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG-nycklar har kofigurerats fel.',
	'securepoll-gpg-parse-error' => 'Ett fel uppstod när GPG-utdatan interpreterades.',
	'securepoll-no-decryption-key' => 'Ingen dekrypteringsnyckel är konfigurerad.
Kan inte dekryptera.',
	'securepoll-list-title' => 'Visa röster: $1',
	'securepoll-header-timestamp' => 'Tid',
	'securepoll-header-user-name' => 'Namn',
	'securepoll-header-user-domain' => 'Domän',
);

/** Telugu (తెలుగు)
 * @author Veeven
 */
$messages['te'] = array(
	'securepoll-header-user-name' => 'పేరు',
	'securepoll-header-details' => 'వివరాలు',
	'securepoll-strike-reason' => 'కారణం:',
	'securepoll-details-link' => 'వివరాలు',
	'securepoll-header-action' => 'చర్య',
	'securepoll-header-reason' => 'కారణం',
);

/** Turkish (Türkçe)
 * @author Joseph
 */
$messages['tr'] = array(
	'securepoll' => 'GüvenliAnket',
	'securepoll-desc' => 'Seçimler ve anketler için eklenti',
	'securepoll-invalid-page' => 'Geçersiz altsayfa "<nowiki>$1</nowiki>"',
	'securepoll-too-few-params' => 'Yeterli altsayfa parametresi yok (geçersiz bağlantı).',
	'securepoll-invalid-election' => '"$1" geçerli bir seçim IDsi değil.',
	'securepoll-not-authorised' => 'Bu seçimlerde oy kullanmak için yetkilendirilmemişsiniz.',
	'securepoll-welcome' => '<strong>Hoş Geldin $1!</strong>',
	'securepoll-not-started' => 'Bu seçimi henüz başlamadı.
$1 tarihinde başlaması planlanıyor.',
	'securepoll-not-qualified' => 'Bu seçimlerde oy kullanmak için yetkili değilsiniz: $1',
	'securepoll-change-disallowed' => 'Bu seçimde daha önce oy kullandınız.
Üzgünüz, tekrar oy kullanamayabilirsiniz.',
	'securepoll-change-allowed' => '<strong>Not: Bu seçimde daha önce oy kullandınız.</strong>
Aşağıdaki formu göndererek oyunuzu değiştirebilirsiniz.
Eğer bunu yaparsanız, orjinal oyunuzun iptal edileceğini unutmayın.',
	'securepoll-submit' => 'Oyu gönder',
	'securepoll-gpg-receipt' => 'Oy verdiğiniz için teşekkürler.

Eğer dilerseniz, aşağıdaki makbuzu oyunuzun delili olarak muhafaza edebilirsiniz:

<pre>$1</pre>',
	'securepoll-thanks' => 'Teşekkürler, oyunuz kaydedildi.',
	'securepoll-return' => "$1'e geri dön",
	'securepoll-encrypt-error' => 'Oy kaydınızın şifrelenmesi başarısız oldu.
Oyunuz kaydedilmedi!

$1',
	'securepoll-no-gpg-home' => 'GPG ev dizini oluşturulamıyor.',
	'securepoll-secret-gpg-error' => 'GPG çalıştırırken hata.
Daha fazla ayrıntı göstermek için LocalSettings.php\'de $wgSecurePollShowErrorDetail=true kullanın.',
	'securepoll-full-gpg-error' => 'GPG çalıştırırken hata:

Komut: $1

Hata:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG anahtarları yanlış yapılandırılmış.',
	'securepoll-gpg-parse-error' => 'GPG çıktısı yorumlanırken hata.',
	'securepoll-no-decryption-key' => 'Hiç deşifre anahtarı ayarlanmamış.
Deşifrelenemiyor.',
);

/** Urdu (اردو)
 * @author محبوب عالم
 */
$messages['ur'] = array(
	'securepoll-desc' => 'توسیعہ برائے انتخابات و مساحات',
	'securepoll-invalid-page' => 'غیرصحیح ذیلی‌صفحہ "<nowiki>$1</nowiki>"',
	'securepoll-invalid-election' => '"$1" کوئی معتبر انتخابی شناخت نہیں ہے.',
	'securepoll-not-authorised' => 'آپ کو اِس انتخاب میں رائے دہندگی کی اِجازت نہیں.',
	'securepoll-welcome' => '<strong>خوش آمدید $1!</strong>',
	'securepoll-not-started' => 'چناؤ ابھی شروع نہیں ہوا.

اِس کا آغاز $1 کو ہوگا.',
	'securepoll-not-qualified' => 'آپ اِس چناؤ میں رائےدہندگی کے اہل نہیں: $1',
	'securepoll-change-disallowed' => 'آپ اِس چناؤ میں پہلے رائے دے چکے ہیں.

معذرت، آپ دوبارہ رائے نہیں دے سکتے.',
	'securepoll-change-allowed' => '<strong>یاددہانی: آپ اِس چناؤ میں پہلے رائے دے چکے ہیں.</strong>

آپ درج ذیل تشکیلہ بھیج کر اپنی رائے تبدیل کرسکتے ہیں.

یاد رہے کہ ایسا کرنے سے آپ کی اصل رائے ختم ہوجائے گی.',
	'securepoll-submit' => 'رائے بھیجئے',
	'securepoll-gpg-receipt' => 'رائےدہندگی کا شکریہ.

اگر آپ چاہیں تو درج ذیل رسید کو اپنی رائےدہندگی کے ثبوت کے طور پر رکھ سکتے ہیں:

<pre>$1</pre>',
	'securepoll-thanks' => 'شکریہ، آپ کی رائے محفوظ کرلی گئی.',
	'securepoll-return' => 'واپس بطرف $1',
);

/** Veps (Vepsan kel')
 * @author Игорь Бродский
 */
$messages['vep'] = array(
	'securepoll-header-timestamp' => 'Aig',
	'securepoll-header-user-name' => 'Nimi',
	'securepoll-header-user-domain' => 'Domen',
	'securepoll-header-ua' => 'Kävutajan agent',
	'securepoll-strike-reason' => 'Sü:',
	'securepoll-strike-cancel' => 'Heitta pätand',
	'securepoll-header-action' => 'Tego',
	'securepoll-header-reason' => 'Sü',
	'securepoll-header-admin' => 'Admin',
);

/** Vietnamese (Tiếng Việt)
 * @author Minh Nguyen
 * @author Vinhtantran
 */
$messages['vi'] = array(
	'securepoll' => 'Bỏ phiếu An toàn',
	'securepoll-desc' => 'Bộ mở rộng dành cho bầu cử và thăm dò ý kiến',
	'securepoll-invalid-page' => 'Trang con không hợp lệ “<nowiki>$1</nowiki>”',
	'securepoll-too-few-params' => 'Không đủ thông số trang con (liên kết không hợp lệ).',
	'securepoll-invalid-election' => '“$1” không phải là mã số bầu cử hợp lệ.',
	'securepoll-not-authorised' => 'Bạn không đủ quyền bỏ phiếu trong cuộc bầu cử này.',
	'securepoll-welcome' => '<strong>Xin chào $1!</strong>',
	'securepoll-not-started' => 'Cuộc bầu cử này chưa bắt đầu.
Dự kiến nó sẽ bắt đầu vào $1.',
	'securepoll-not-qualified' => 'Bạn không đủ tiêu chuẩn để bỏ phiếu trong cuộc bầu cử này: $1',
	'securepoll-change-disallowed' => 'Bạn đã bỏ phiếu cho cuộc bầu cử này rồi.
Rất tiếc, bạn không thể bỏ phiếu được nữa.',
	'securepoll-change-allowed' => '<strong>Chú ý: Bạn đã bỏ phiếu trong cuộc bầu cử này rồi.</strong>
Bạn có thể thay đổi phiếu bầu bằng cách điền vào mẫu đơn phía dưới.
Ghi nhớ rằng nếu bạn làm điều này, phiếu bầu trước đây của bạn sẽ bị hủy.',
	'securepoll-submit' => 'Gửi phiếu bầu',
	'securepoll-gpg-receipt' => 'Cảm ơn bạn đã tham gia bỏ phiếu.

Nếu muốn, bạn có thể nhận biên lai sau để làm bằng chứng cho phiếu bầu của bạn:

<pre>$1</pre>',
	'securepoll-thanks' => 'Cảm ơn, phiếu bầu của bạn đã được ghi nhận.',
	'securepoll-return' => 'Trở về $1',
	'securepoll-encrypt-error' => 'Không thể mã hóa phiếu bầu của bạn.
Việc bỏ phiếu của bạn chưa được ghi lại!

$1',
	'securepoll-no-gpg-home' => 'Không thể khởi tạo thư mục nhà GPG',
	'securepoll-secret-gpg-error' => 'Có lỗi khi xử lý GPG.
Hãy dùng $wgSecurePollShowErrorDetail=true; trong LocalSettings.php để hiển thị thêm chi tiết.',
	'securepoll-full-gpg-error' => 'Có lỗi khi xử lý GPG:

Lệnh: $1

Lỗi:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'Khóa GPG không được cấu hình đúng.',
	'securepoll-gpg-parse-error' => 'Có lỗi khi thông dịch dữ liệu xuất GPG.',
	'securepoll-no-decryption-key' => 'Chưa cấu hình khóa giải mã.
Không thể giải mã.',
	'securepoll-list-title' => 'Liệt kê các lá phiếu: $1',
	'securepoll-header-timestamp' => 'Thời điểm',
	'securepoll-header-user-name' => 'Tên',
	'securepoll-header-user-domain' => 'Tên miền',
	'securepoll-header-ua' => 'Trình duyệt',
	'securepoll-header-strike' => 'Gạch bỏ',
	'securepoll-header-details' => 'Chi tiết',
	'securepoll-strike-button' => 'Gạch bỏ',
	'securepoll-unstrike-button' => 'Phục hồi',
	'securepoll-strike-reason' => 'Lý do:',
	'securepoll-strike-cancel' => 'Hủy bỏ',
	'securepoll-strike-error' => 'Lỗi khi gạch bỏ hay phục hồi: $1',
	'securepoll-need-admin' => 'Chỉ các quản lý viên có quyền thực hiện tác vụ này.',
	'securepoll-details-link' => 'Chi tiết',
	'securepoll-details-title' => 'Chi tiết lá phiếu: #$1',
	'securepoll-invalid-vote' => '“$1” không phải là ID lá phiếu hợp lệ',
	'securepoll-header-user-type' => 'Loại người dùng',
	'securepoll-voter-properties' => 'Thuộc tính cử tri',
	'securepoll-strike-log' => 'Nhật trình gạch bỏ',
	'securepoll-header-action' => 'Tác vụ',
	'securepoll-header-reason' => 'Lý do',
	'securepoll-header-admin' => 'Quản lý viên',
	'securepoll-dump-title' => 'Kết xuất: $1',
	'securepoll-dump-no-crypt' => 'Không có sẵn hồ sơ mật mã hóa cho cuộc bầu cử này, vì tính năng mật mã hóa không được thiết lập cho cuộc bầu cử.',
	'securepoll-dump-not-finished' => 'Các hồ sơ bầu cử mật mã hóa chỉ có sẵn sau ngày kết thúc: $1',
	'securepoll-dump-no-urandom' => 'Không thể mở /dev/urandom.
Để giữ gìn tính riêng tư của các cử tri, các hồ sơ bầu cử mật mã hóa cần được ngẫu nhiên hóa dùng dòng số ngẫu nhiên mã hóa trước khi công khai.',
	'securepoll-translate-title' => 'Biên dịch: $1',
	'securepoll-invalid-language' => 'Mã ngôn ngữ “$1” không hợp lệ',
	'securepoll-submit-translate' => 'Cập nhật',
	'securepoll-language-label' => 'Chọn ngôn ngữ:',
	'securepoll-submit-select-lang' => 'Biên dịch',
);

/** Simplified Chinese (‪中文(简体)‬)
 * @author Bencmq
 * @author Skjackey tse
 */
$messages['zh-hans'] = array(
	'securepoll' => '安全投票',
	'securepoll-desc' => '选举和投票扩展',
	'securepoll-invalid-page' => '无效的子页面「<nowiki>$1</nowiki>」',
	'securepoll-too-few-params' => '缺少子页面参数（无效链接）。',
	'securepoll-invalid-election' => '「$1」不是有效的选举编号。',
	'securepoll-not-authorised' => '您未被授权参与本次选举。',
	'securepoll-welcome' => '<strong>欢迎$1！</strong>',
	'securepoll-not-started' => '这个选举尚未开始。
按计划将于$1开始。',
	'securepoll-not-qualified' => '您不具有参与选举的资格：$1',
	'securepoll-change-disallowed' => '您已经参与过本次选举。
对不起，您不能再次投票。',
	'securepoll-change-allowed' => '<strong>注意：您已经在本次选举中投过票。</strong>
您可以提交下面的表格并更改您的投票。
请注意若您更改投票，原先的投票将作废。',
	'securepoll-submit' => '提交投票',
	'securepoll-gpg-receipt' => '感谢您的投票。

您可以保留下面的回执作为您参与投票的证据：

<pre>$1</pre>',
	'securepoll-thanks' => '谢谢您，您的投票已被记录。',
	'securepoll-return' => '回到$1',
	'securepoll-encrypt-error' => '投票记录加密失败。
您的投票未被记录。

$1',
	'securepoll-no-gpg-home' => '无法创建GPG主目录。',
	'securepoll-secret-gpg-error' => '执行GPG出错。
在LocalSettings.php中使用$wgSecurePollShowErrorDetail=true;以查看更多细节。',
	'securepoll-full-gpg-error' => '执行GPG错误：

命令：$1

错误：
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG密匙配置错误。',
	'securepoll-gpg-parse-error' => '解释GPG输出时出错。',
	'securepoll-no-decryption-key' => '解密密匙未配置。
无法解密。',
);

/** Traditional Chinese (‪中文(繁體)‬)
 * @author Skjackey tse
 * @author Wong128hk
 */
$messages['zh-hant'] = array(
	'securepoll' => '安全投票',
	'securepoll-desc' => '投票及選舉擴展',
	'securepoll-invalid-page' => '無效的子頁面「<nowiki>$1</nowiki>」',
	'securepoll-too-few-params' => '缺少子頁面參數（無效鏈接）。',
	'securepoll-invalid-election' => '「$1」不是有效的選舉編號。',
	'securepoll-not-authorised' => '您未被授權參與本次選舉。',
	'securepoll-welcome' => '<strong>歡迎$1！</strong>',
	'securepoll-not-started' => '這個選舉尚未開始。
按計劃將於$1開始。',
	'securepoll-not-qualified' => '您不具有於是次選舉中參與表決的資格︰$1',
	'securepoll-change-disallowed' => '您已於是次選舉中投票。
閣下恕未可再次投票。',
	'securepoll-change-allowed' => '<strong>請注意您已於較早前於是次選舉中投票。</strong>
您可以透過遞交以下的表格改動您的投票。
惟請注意，若然閣下作出此番舉動，閣下原先所投之票將變為廢票。',
	'securepoll-submit' => '遞交投票',
	'securepoll-gpg-receipt' => '多謝您參與投票。

閣下可以保留以下收條以作為參與過是次投票的憑證︰

<pre>$1</pre>',
	'securepoll-thanks' => '感謝，閣下的投票已被紀錄。',
	'securepoll-return' => '回到$1',
	'securepoll-encrypt-error' => '投票紀錄加密失敗。
您的投票未被紀錄。

$1',
	'securepoll-no-gpg-home' => '無法建立GPG主目錄。',
	'securepoll-secret-gpg-error' => '執行GPG出錯。
於LocalSettings.php中使用$wgSecurePollShowErrorDetail=true;以展示更多細節。',
	'securepoll-full-gpg-error' => '執行GPG錯誤：

命令：$1

錯誤：
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG密匙配置錯誤。',
	'securepoll-gpg-parse-error' => '解釋GPG輸出時出錯。',
	'securepoll-no-decryption-key' => '解密密匙未配置。
無法解密。',
);

