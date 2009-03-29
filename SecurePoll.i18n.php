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
	'securepoll' => 'SecurePoll',
	'securepoll-desc' => 'Extension for elections and surveys',
	'securepoll-invalid-page' => 'Invalid subpage "<nowiki>$1</nowiki>"',
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
);

/** Message documentation (Message documentation)
 * @author Fryed-peach
 */
$messages['qqq'] = array(
	'securepoll-return' => '{{Identical|Return to $1}}',
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
	'securepoll-not-authorised' => 'Du bist nicht berechtigt, in dieser Wahl abzustimmen.',
	'securepoll-welcome' => '<strong>Willkommen $1!</strong>',
	'securepoll-not-started' => 'Diese Wahl hat noch nicht begonnen.
Sie beginnt voraussichtlich am $1.',
	'securepoll-not-qualified' => 'Du bist nicht qualifiziert, in dieser Wahl abzustimmen: $1',
	'securepoll-change-disallowed' => 'Du hast bereits in dieser Wahl abgestimmt.
Du darfst nicht erneut abstimmen.',
	'securepoll-change-allowed' => '<strong>Hinweis: Du hast in dieser Wahl bereits abgestimmt.</strong>
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
	'securepoll-no-gpg-home' => 'GPG-Heimverzeichnis kann nicht erstellt werden.',
	'securepoll-secret-gpg-error' => 'Fehler beim Ausführen von GPG.
$wgSecurePollShowErrorDetail=true; in LocalSettings.php einfügen, um mehr Details anzuzeigen.',
	'securepoll-full-gpg-error' => 'Fehler beim Ausführen von GPG:

Befehl: $1

Fehler:
<pre>$2</pre>',
	'securepoll-gpg-config-error' => 'GPG-Schlüssel sind nicht korrekt konfiguriert.',
	'securepoll-gpg-parse-error' => 'Fehler beim Interpretieren der Ausgabe von GPG.',
	'securepoll-no-decryption-key' => 'Es ist kein Entschlüsselungsschlüssel konfiguriert.
Entschlüsselung nicht möglich.',
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

/** Greek (Ελληνικά)
 * @author Geraki
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
);

/** Spanish (Español)
 * @author DoveBirkoff
 */
$messages['es'] = array(
	'securepoll-invalid-election' => '"$1" no es un identificador de elección valido.',
	'securepoll-not-authorised' => 'No estás autorizado para votar en esta elección.',
	'securepoll-not-qualified' => 'No cumples los requisitos para votar en esta elección: $1',
	'securepoll-change-disallowed' => 'Ya has votado antes en esta elección.
Lo siento, no puede votar de nuevo.',
);

/** French (Français)
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
	'securepoll-invalid-election' => '"$1" non es un identificator de election valide.',
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
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'securepoll' => 'Securiséiert Ëmfro',
	'securepoll-desc' => 'Erweiderung fir Walen an Ëmfroen',
	'securepoll-invalid-page' => 'Net-valabel Ënnersäit "<nowiki>$1</nowiki>"',
	'securepoll-not-authorised' => 'Dir sidd net berechtegt fir bäi dëse Wale mattzemaachen.',
	'securepoll-welcome' => '<strong>Wëllkomm $1!</strong>',
	'securepoll-not-qualified' => 'Dir sidd net qualifizéiert fir bäi dëse Walen ofzestëmmen: $1',
	'securepoll-change-disallowed' => 'Dir hutt bäi dëse Walen virdru schonn ofgestëmmt.
Pardon, mee dir däerft net nach eng Kéier ofstëmmen.',
	'securepoll-submit' => 'Stëmm ofginn',
	'securepoll-thanks' => 'Merci, Är Stëmm gouf gespäichert.',
	'securepoll-return' => 'Zréck op $1',
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
	'securepoll-thanks' => 'Hartelijk dank. Uw stem is ontvangen en opgeslagen.',
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

