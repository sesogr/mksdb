<?php declare(strict_types=1);
const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
[$isForm, $path, $host, $schema, $username, $password] = showPage(
    sprintf('%s://%s', $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_HOST']),
    $_SERVER['DOCUMENT_ROOT'],
    dirname($_SERVER['SCRIPT_NAME']),
    dirname($_SERVER['SCRIPT_NAME']) . '/progress.json',
    $_POST['cku3chddh0000p386r3190y81'] ?? '',
    $_POST['cku3d53lb0003p386qbirzhvq'] ?? 'localhost',
    $_POST['cku3dbtrq0004p386ct13vmhx'] ?? '',
    $_POST['cku3dcdu50006p38620xa9iqe'] ?? '',
    $_POST['cku3dcjh10007p386jefnoz8r'] ?? ''
);
if (!$isForm) {
    out(print_r([$path, $host, $schema, $username, $password], true));
}
//
function clear() {
    echo "<script>clear()</script>\n";
    flush();
}
function out($string)
{
    printf("<script>write(%s);</script>\n", json_encode($string, JSON_FLAGS));
    flush();
}
function persist() {
    echo "<script>persist()</script>\n";
    flush();
}
function reload() {
    echo "<script>location.href=location.href</script>\n";
    flush();
}
function setTitle($string) {
    printf("<script>document.title=%s</script>\n", json_encode($string, JSON_FLAGS));
    flush();
}
function showPage (string $baseUri, string $docRoot, string $installDir, string $progressFile, string $path, string $host, string $schema, string $username, string $password) {
    $showForm = !is_file($progressFile) && 5 > count(array_filter([$path, $host, $schema, $username, $password]));
?><!DOCTYPE html>
<html lang="de">
    <head>
        <title>Installation</title>
        <script>
            <?php if ($showForm): ?>

            document.addEventListener('DOMContentLoaded', () => {
                const [input, url, path, apiUrl] = [
                    'cku3chddh0000p386r3190y81',
                    'cku3g67ql0008p386s8bc388w',
                    'cku3clqhv0001p38631apyrki',
                    'cku3cmhqv0002p386ifj66d5r',
                ].map(id=>document.getElementById(id));
                const updatePaths = () => {
                    path.innerHTML = input.value ? input.value.replace(/^\/+|\/+$/g, '') + '/' : '';
                    url.innerHTML = apiUrl.innerHTML = input.value ? encodeURI(input.value.replace(/^\/+|\/+$/g, '')) + '/' : '';
                };
                input.addEventListener('keyup', updatePaths);
                updatePaths();
            });
            <?php else: ?>

            function clear() {
                const blocks = document.body.getElementsByTagName('div');
                document.body.removeChild(blocks[blocks.length - 1]);
                persist();
            }
            function persist() {
                document.body.appendChild(document.createElement('div'));
            }
            function write(text) {
                const blocks = document.body.getElementsByTagName('div');
                blocks[blocks.length - 1].appendChild(document.createTextNode(text));
                document.documentElement.scrollTop = document.documentElement.scrollHeight;
            }
            <?php endif ?>

        </script>
        <style>
            body{margin:24px;font-family:sans-serif}
            div,fieldset{margin:12px 0}
            div{white-space:pre}
            table{border-collapse:collapse}
            th,td{text-align:left;padding:6px 12px;vertical-align:baseline}
            thead+tbody th,thead+tbody td{border-top:1px solid #ccc}
        </style>
    </head>
    <body>
        <?php if ($showForm): ?>

        <form action="" method="post">
            <fieldset>
                <legend>Verzeichnisse</legend>
                <table>
                    <thead>
                    <tr>
                        <th></th>
                        <th>Installer</th>
                        <th><label for="cku3chddh0000p386r3190y81">Suche</label></th>
                        <th>API</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <th><label for="cku3chddh0000p386r3190y81">Verzeichnis</label></th>
                        <td><?php echo realpath(__DIR__) ?></td>
                        <td style="white-space:nowrap"><?php echo $docRoot ?>/<input id="cku3chddh0000p386r3190y81" name="cku3chddh0000p386r3190y81" value="<?php echo htmlspecialchars($path)?>" /></td>
                        <td><?php echo $docRoot ?>/<span id="cku3clqhv0001p38631apyrki"></span>api</td>
                    </tr>
                    <tr>
                        <th>URL</th>
                        <td><?php echo sprintf('%s%s', $baseUri, $installDir) ?></td>
                        <td><?php echo $baseUri ?>/<span id="cku3g67ql0008p386s8bc388w"></span></td>
                        <td><?php echo $baseUri ?>/<span id="cku3cmhqv0002p386ifj66d5r"></span>api</td>
                    </tr>
                    <tr>
                        <th>Bemerkungen</th>
                        <td>Das ist die gerade angezeigte Seite. Sie wird nach erfolgreicher Installation gelöscht.</td>
                        <td>Das ist die Seite, unter der die Suche zu finden sein wird.</td>
                        <td>Das ist der Endpunkt der Schnittstelle, die intern die Daten liefert, aber nicht direkt von Menschen benutzt wird.</td>
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
                        <td><input id="cku3d53lb0003p386qbirzhvq" name="cku3d53lb0003p386qbirzhvq" value="<?php echo htmlspecialchars($host)?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="cku3dbtrq0004p386ct13vmhx">Datenbankname</label></th>
                        <td><input id="cku3dbtrq0004p386ct13vmhx" name="cku3dbtrq0004p386ct13vmhx" placeholder="z. B. schlager" value="<?php echo htmlspecialchars($schema)?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="cku3dcdu50006p38620xa9iqe">Benutzername</label></th>
                        <td><input id="cku3dcdu50006p38620xa9iqe" name="cku3dcdu50006p38620xa9iqe" placeholder="z. B. schlager" value="<?php echo htmlspecialchars($username)?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="cku3dcjh10007p386jefnoz8r">Kennwort</label></th>
                        <td><input id="cku3dcjh10007p386jefnoz8r" name="cku3dcjh10007p386jefnoz8r" placeholder="z. B. zorofzoftumev" value="<?php echo htmlspecialchars($password)?>" /></td>
                    </tr>
                    </tbody>
                </table>
            </fieldset>
            <label>
                <button type="submit">Installieren</button>
            </label>
        </form>
        <?php else: ?>

        <div></div>
        <?php endif ?>

    </body>
</html><?php return [$showForm, sprintf("%s/%s", $docRoot, $path), $host, $schema, $username, $password];
}
