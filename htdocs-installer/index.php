<?php declare(strict_types=1);
set_time_limit(0);
set_error_handler(function (int $code, string $message, ?string $file, ?int $line, ?array $context = []) {
    printf("<script>i(%s);c(1);a();s()</script>\n", json_encode($message, JSON_FLAGS));
    flush();
});
const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
sendHtml(
    $_POST['cku3chddh0000p386r3190y81'] ?? '',
    $_POST['cku3d53lb0003p386qbirzhvq'] ?? 'localhost',
    $_POST['cku3dbtrq0004p386ct13vmhx'] ?? '',
    $_POST['cku3dcdu50006p38620xa9iqe'] ?? '',
    $_POST['cku3dcjh10007p386jefnoz8r'] ?? '',
    sprintf('%s://%s', $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_HOST']),
    $_SERVER['DOCUMENT_ROOT'],
    __DIR__,
    'progress.json'
);
//
function step1(): Generator
{
    yield 'Starting step 1.';
    time_nanosleep(1, (int)1e8);
    yield 'In Progress...';
    time_nanosleep(1, (int)1e8);
    if (mt_rand(0, 1)) return 'Finished step 1.';
    throw new Exception('Step 1 failed');
}
//
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

function makePage(string $baseUri, string $docRoot, string $installDir, string $path, string $host, string $schema, string $username, string $password, bool $showForm): string
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

function sendHtml($path, $host, $schema, $username, $password, $baseUri, $docRoot, $installerDir, $progressFileName): void
{
    $progressFile = sprintf("%s/%s", $installerDir, $progressFileName);
    $showForm = is_file($progressFile) && 4 > count(array_filter([$host, $schema, $username, $password]));
    echo makePage($baseUri, $docRoot, $installerDir, $path, $host, $schema, $username, $password, $showForm);
    if (!is_file($progressFile)) {
        printf("<script>i(%s);s()</script>\n", json_encode('Prüfe Schreibrechte für Aufgabenliste...<br />', JSON_FLAGS));
        flush();
        sleep(1);
        printf(
            file_put_contents($progressFile, "step1\nstep1\n")
                ? "<script>i('Aufgabenliste gespeichert.');c(0);a();i('Lade neu...');s();r(1)</script>\n"
                :"<script>i('Fehler beim Anlegen der Datei %s<br />Bitte Schreibrechte des Verzeichnisses prüfen.');c(1);s()</script>\n",
            $progressFile
        );
        flush();
    } elseif (!$showForm) {
        /** @var closure[] $tasks */
        $tasks = array_fill(0, 32, 'step1');
        foreach ($tasks as $task) {
            $isError = false;
            try {
                /** @var Generator $generator */
                $generator = $task();
                foreach ($generator as $message) {
                    printf("<script>i(%s);s()</script>\n", json_encode($message . '<br />', JSON_FLAGS));
                    flush();
                }
                $message = $generator->getReturn();
            } catch (Exception $e) {
                $isError = true;
                $message = $e->getMessage();
            }
            printf("<script>i(%s);c(%d);a();s()</script>\n", json_encode($message, JSON_FLAGS), $isError);
            flush();
            if ($isError) break;
        }
        echo "<script>c(2)</script>\n";
        flush();
    }
}
