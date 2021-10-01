<?php declare(strict_types=1);
set_time_limit(0);
set_error_handler(function (int $code, string $message, ?string $file, ?int $line, ?array $context = []) {
    sendUpdate('i(%s);c(1);a();s()', json_encode($message, JSON_FLAGS));
});
const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
run(
    $_POST['cku3chddh0000p386r3190y81'] ?? '',
    $_POST['cku3d53lb0003p386qbirzhvq'] ?? 'sedna-soft.de',
    $_POST['cku3dbtrq0004p386ct13vmhx'] ?? 'dbmks',
    $_POST['cku3dcdu50006p38620xa9iqe'] ?? 'dbmks',
    $_POST['cku3dcjh10007p386jefnoz8r'] ?? 'WewgArtYeshUb3',
    sprintf('%s://%s', $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_HOST']),
    $_SERVER['DOCUMENT_ROOT'],
    __DIR__,
    'queue.txt'
);
//
function applyDbOperations(string $host, string $schema, string $username, string $password): Generator {
    $last = null;
    [$host, $port] = explode(':', $host . ':3306:', 3);
    try {
        $db = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $schema),
            $username,
            $password,
            [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        throw new Exception('Fehler beim Verbindungsaufbau, bitte Datenbank-Angaben korrigieren.');
    }
    yield 'Lade operations-compact.sql herunter...';
    foreach (file('https://raw.githubusercontent.com/sesogr/mksdb/master/operations-compact.sql') as $command) {
        [$type,, $name] = explode(' ', $command . '   ', 4);
        if ($type === 'insert') {
            yield sprintf(
                'Erstelle Einträge für %s... %s',
                $name,
                strpos($name, '_x_') ? '(Dieser Schritt kann mehrere Minuten in Anspruch nehmen.)' : ''
            );
        } elseif ($type !== $last) {
            switch ($type) {
                case 'drop': yield 'Entferne alte Tabellen...'; break;
                case 'create': yield 'Erstelle Tabellen...'; break;
                case 'alter': yield 'Optimiere Tabellen...'; break;
            }
        }
        $last = $type;
        if ($command) {
            $db->exec($command);
        }
    }
    return 'Berichtstabellen vollständig aufgebaut.';
}

function importDataDump(string $url, string $host, string $schema, string $username, string $password, bool $isFirst = false): Generator
{
    if ($isFirst) {
        yield 'Prüfe Datenbank-Verbindung...';
    }
    [$host, $port] = explode(':', $host . ':3306:', 3);
    try {
        $db = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $schema),
            $username,
            $password,
            [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        throw new Exception('Fehler beim Verbindungsaufbau, bitte Datenbank-Angaben korrigieren.');
    }
    if ($db->query("show tables like '20201217-oeaw-schlager-db'")->rowCount() === 1
        && $db->query('select count(*) from `20201217-oeaw-schlager-db`')->fetchColumn() == 14710) {
        return $isFirst ? 'Mastertabelle ist vollständig vorhanden, Import übersprungen.' : null;
    }
    yield sprintf('Lade %s herunter...', basename($url));
    $commands = explode(";\nINSERT INTO ", file_get_contents($url));
    foreach ($commands as $i => $command) {
        yield sprintf('Importiere Daten... %.1f%%', $i / count($commands) * 100);
        $db->exec(sprintf('%s%s', $i ? 'INSERT INTO ' : '', $command));
    }
    return sprintf('Datenpaket %s importiert.', basename($url));
}

function recreateStripPunctuation(string $host, string $schema, string $username, string $password): Generator {
    $drop = 'drop function if exists strip_punctuation';
    $create = <<<'SQL'
        create function strip_punctuation(input text) returns text deterministic
        begin
            declare text1, text2, text3, text4, text5, text6, text7 text;
            set text1 = replace(replace(replace(replace(replace(input, '`', ' '), '=', ' '), '[', ' '), ']', ' '), ';', ' ');
            set text2 = replace(replace(replace(replace(replace(text1, '\'', ' '), '\\', ' '), ',', ' '), '.', ' '), '/', ' ');
            set text3 = replace(replace(replace(replace(replace(text2, '~', ' '), '!', ' '), '@', ' '), '#', ' '), '$', ' ');
            set text4 = replace(replace(replace(replace(replace(text3, '%', ' '), '^', ' '), '&', ' '), '*', ' '), '(', ' ');
            set text5 = replace(replace(replace(replace(replace(text4, ')', ' '), '_', ' '), '+', ' '), '\{', ' '), '}', ' ');
            set text6 = replace(replace(replace(replace(replace(text5, ':', ' '), '"', ' '), '|', ' '), '<', ' '), '>', ' ');
            set text7 = replace(replace(replace(replace(text6, '?', ' '), '         ', ' '), '     ', ' '), '   ', ' ');
            return replace(replace(text7, '  ', ' '), ' ', ' ');
        end
        SQL;
    [$host, $port] = explode(':', $host . ':3306:', 3);
    try {
        $db = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $schema),
            $username,
            $password,
            [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION]
        );
        yield 'Entferne alte Version von strip_punctuation...';
        $db->exec($drop);
        time_nanosleep(0, 500000000);
        yield 'Installiere neue Version von strip_punctuation...';
        $db->exec($create);
        time_nanosleep(0, 500000000);
    } catch (PDOException $e) {
        throw new Exception('Fehler beim Verbindungsaufbau, bitte Datenbank-Angaben korrigieren.');
    }
    return 'Hilfsfunktion strip_punctuation installiert.';
}

function step(int $step): Generator
{
    yield sprintf("Starting step %d.", $step);
    time_nanosleep(1, (int)1e8);
    yield 'In Progress...';
    time_nanosleep(1, (int)1e8);
    if (mt_rand(0, 1)) return sprintf("Finished step %d.", $step);
    throw new Exception(sprintf("Step %d failed.", $step));
}
//
function dequeue(string $queuePath): ?Closure {
    $lines = file($queuePath);
    if (empty($lines)) {
        return null;
    }
    $first = array_shift($lines);
    [$function, $args] = explode(':', $first, 2);
    return function () use ($function, $args, $queuePath, $lines) {
        /** @var Generator $result */
        $result = $function(...json_decode(gzuncompress(base64_decode($args))));
        yield from $result;
        file_put_contents($queuePath, $lines);
        return $result->getReturn();
    };
}

function enqueue(string $queuePath, callable $function, ...$args): bool {
    return (bool)file_put_contents(
        $queuePath,
        sprintf("%s:%s\n", $function, base64_encode(gzcompress(json_encode($args, JSON_FLAGS)))),
        FILE_APPEND
    );
}

function initialiseQueue(string $progressFile, string $baseUri, string $docRoot, string $installDir, string $path, string $host, string $schema, string $username, string $password): void
{
    enqueue($progressFile, 'importDataDump', 'https://raw.githubusercontent.com/sesogr/mksdb/master/csv-import/4-parts/part-1-of-4.sql', $host, $schema, $username, $password, true);
    enqueue($progressFile, 'importDataDump', 'https://raw.githubusercontent.com/sesogr/mksdb/master/csv-import/4-parts/part-2-of-4.sql', $host, $schema, $username, $password);
    enqueue($progressFile, 'importDataDump', 'https://raw.githubusercontent.com/sesogr/mksdb/master/csv-import/4-parts/part-3-of-4.sql', $host, $schema, $username, $password);
    enqueue($progressFile, 'importDataDump', 'https://raw.githubusercontent.com/sesogr/mksdb/master/csv-import/4-parts/part-4-of-4.sql', $host, $schema, $username, $password);
    enqueue($progressFile, 'recreateStripPunctuation', $host, $schema, $username, $password);
    enqueue($progressFile, 'applyDbOperations', $host, $schema, $username, $password);
    enqueue($progressFile, 'step', 3);
    enqueue($progressFile, 'step', 4);
}

function makeForm(string $baseUri, string $docRoot, string $installDir, string $path, string $host, string $schema, string $username, string $password): string
{
    return sprintf(
        <<<'HTML'

                <form action="" method="post">
                    <fieldset>
                        <legend>Verzeichnisse</legend>
                        <table>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th><label for="cku3chddh0000p386r3190y81">Verzeichnis</label></th>
                                    <th>URL</th>
                                    <th>Bemerkungen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>Installer</th>
                                    <td>%s</td>
                                    <td>%s%s/</td>
                                    <td>Das ist die gerade angezeigte Seite. Sie wird nach erfolgreicher Installation gelöscht.</td>
                                </tr>
                                <tr>
                                    <th><label for="cku3chddh0000p386r3190y81">Suche</label></th>
                                    <td style="white-space:nowrap">%s/<input id="cku3chddh0000p386r3190y81" name="cku3chddh0000p386r3190y81" value="%s"/> </td>
                                    <td>%2$s/<span id="cku3g67ql0008p386s8bc388w"></span></td>
                                    <td>Das ist die Seite, unter der die Suche zu finden sein wird.</td>
                                </tr>
                                <tr>
                                    <th>API</th>
                                    <td>%4$s/<span id="cku3clqhv0001p38631apyrki"></span>api</td>
                                    <td>%2$s/<span id="cku3cmhqv0002p386ifj66d5r"></span>api/</td>
                                    <td>Das ist der Endpunkt der Schnittstelle, die intern die Daten liefert, aber nicht direkt von Menschen benutzt wird. </td>
                                </tr>
                            </tbody>
                        </table>
                    </fieldset>
                    <fieldset>
                        <legend>Datenbank</legend>
                        <table>
                            <tbody>
                                <tr>
                                    <th><label for="cku3d53lb0003p386qbirzhvq">Host</label></th>
                                    <td><input id="cku3d53lb0003p386qbirzhvq" name="cku3d53lb0003p386qbirzhvq" value="%s"/></td>
                                </tr>
                                <tr>
                                    <th><label for="cku3dbtrq0004p386ct13vmhx">Datenbankname</label></th>
                                    <td><input id="cku3dbtrq0004p386ct13vmhx" name="cku3dbtrq0004p386ct13vmhx" placeholder="z. B. schlager" value="%s"/></td>
                                </tr>
                                <tr>
                                    <th><label for="cku3dcdu50006p38620xa9iqe">Benutzername</label></th>
                                    <td><input id="cku3dcdu50006p38620xa9iqe" name="cku3dcdu50006p38620xa9iqe" placeholder="z. B. schlager" value="%s"/></td>
                                </tr>
                                <tr>
                                    <th><label for="cku3dcjh10007p386jefnoz8r">Kennwort</label></th>
                                    <td><input id="cku3dcjh10007p386jefnoz8r" name="cku3dcjh10007p386jefnoz8r" placeholder="z. B. zorofzoftumev" value="%s"/></td>
                                </tr>
                            </tbody>
                        </table>
                    </fieldset>
                    <label>
                        <button type="submit">Installieren</button>
                    </label>
                </form>
        HTML,
        htmlspecialchars(realpath(__DIR__)),
        htmlspecialchars($baseUri),
        htmlspecialchars($installDir),
        htmlspecialchars($docRoot),
        htmlspecialchars($path),
        htmlspecialchars($host),
        htmlspecialchars($schema),
        htmlspecialchars($username),
        htmlspecialchars($password)
    );
}

function makePage(string $baseUri, string $docRoot, string $installDir, string $path = '', string $host = 'localhost', string $schema = '', string $username = '', string $password = '', bool $showForm = false): string
{
    return sprintf(
        <<<'HTML'
        <!DOCTYPE html>
        <html lang="de">
            <head>
                <title>Installation</title>
                <style>
                    body{margin:36px;font-family:sans-serif}
                    div,fieldset{margin:12px 0}
                    section div{background-color:#fff;border:0 solid #f00;padding:0 6px;transition:all .2s}
                    section div:last-child{background-color:#ff8;border-width:1px;padding:12px}
                    section div.end{border-width:0;padding:0}
                    section div.error{background-color:#fcc;border-width:0;padding:3px 6px}
                    section div.success{background-color:#cfc;border-width:0;padding:3px 6px}
                    table{border-collapse:collapse}
                    th,td{text-align:left;padding:6px 12px;vertical-align:baseline}
                    thead+tbody th,thead+tbody td{border-top:1px solid #ccc}
                </style>
            </head>
            <body>%s
            </body>
        </html>
        <script>%s</script>

        HTML,
        $showForm
            ? makeForm($baseUri, $docRoot, $installDir, $path, $host, $schema, $username, $password)
            : <<<HTML

                    <section>
                        <div></div>
                    </section>
            HTML,
        $showForm
            ? "((a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z,_)=>b[c](d,()=>((á,ƀ,ç,đ)=>(é=>{á[c](z,é);é()})(()=>{ç[v]=á[w]?á[w][x](_,'')+'/':'';ƀ[v]=đ[v]=á[w]?a[y](á[w][x](_,''))+'/':'';}))(...[e+f+g+h+i+j,e+k+g+l+i+m,e+n+g+o+i+p,e+q+g+r+i+s][t](é=>b[u](é)))))(window,document,...'addEventListener:DOMContentLoaded:cku3:chddh:000:0p:386:r3190y81:g67ql:8p:s8bc388w:clqhv:1p:31apyrki:cmhqv:2p:ifj66d5r:map:getElementById:innerHTML:value:replace:encodeURI:keyup'.split(':'),/^\/+|\/+$/g)"
            : "const[a,c,i,r,s]=((a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v)=>((w,x,y)=>((z,á)=>((ƀ,ç)=>[_=>ƀ[c+d](w[e+f](g)),_=>ƀ[ç][h]=[i,j,k][_],_=>ƀ[ç][l]=_,_=>x(()=>y[m]=y[m],_*b),_=>á[n+o]=á[n+p]])(z[q+f+d],r+f+d))(w[s],w[t+f]))(a[t],a[u],a[v]))(window,1e3,...'append:Child:create:Element:div:className:success:error:end:innerHTML:href:scroll:Top:Height:first:last:body:document:setTimeout:location'.split(':'))"
    );
}

function run($path, $host, $schema, $username, $password, $baseUri, $docRoot, $installerDir, $progressFileName): void
{
    $progressFile = sprintf("%s/%s", $installerDir, $progressFileName);
    if (!is_file($progressFile)) {
        echo makePage($baseUri, $docRoot, $installerDir);
        sendUpdate('i(%s);s()', json_encode('Prüfe Schreibrechte für Aufgabenliste...<br />', JSON_FLAGS));
        sleep(1);
        sendUpdate(
            file_put_contents($progressFile, '') !== false
                ? 'i("Aufgabenliste angelegt.");c(0);a();i(\'Lade neu...\');s();r(1)'
                : 'i("Fehler beim Anlegen der Datei %s<br />Bitte Schreibrechte für das Verzeichnis erteilen.");c(1);s()',
            $progressFile
        );
    } elseif (filesize($progressFile) === 0 && count(array_filter([$host, $schema, $username, $password])) < 4) {
        echo makePage($baseUri, $docRoot, $installerDir, $path, $host, $schema, $username, $password, true);
    } else {
        echo makePage($baseUri, $docRoot, $installerDir);
        if (filesize($progressFile) === 0) {
            sendUpdate('i(\'Befülle Aufgabenliste...\');s()');
            initialiseQueue($progressFile, $baseUri, $docRoot, $installerDir, $path, $host, $schema, $username, $password);
            sendUpdate('i(\'Aufgabenliste befüllt.\');c(0);a();s()');
        }
        while ($thunk = dequeue($progressFile)) {
            $isError = false;
            try {
                /** @var Generator $generator */
                $generator = $thunk();
                foreach ($generator as $message) {
                    sendUpdate('i(%s);s()', json_encode($message . '<br />', JSON_FLAGS));
                }
                $message = $generator->getReturn();
            } catch (Exception $e) {
                $isError = true;
                $message = $e->getMessage();
            }
            if ($message) {
                sendUpdate('i(%s);c(%d);a();s()', json_encode($message, JSON_FLAGS), $isError);
            }
            if ($isError) {
                unlink($progressFileName);
                break;
            }
        }
        sendUpdate('c(2)');
    }
}

function sendUpdate(string $commands, ...$args): void {
    printf("<script>%s</script>\n", $args ? sprintf($commands, ...$args) : $commands);
    flush();
}
