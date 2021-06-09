<?php /** @var string $search */?>
<?php // Die folgende Deklaration ermöglicht Code-Unterstützung und -vervollständigung für $results und Unterobjekte ?>
<?php /** @var $results SearchResult[] */?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Schlagerdatenbank Ergebnisse</title>
    </head>
    <body>
        <h1>Suche</h1>
        <form action="" method="post">
            <label>
                <input type="text" name="ce1a388c" value="<?php out($search)?>"/>
            </label>
            <label>
                <button type="submit">Suchen</button>
            </label>
            <label>
                <button type="button" onclick="this.form.ce1a388c.value = ''; this.form.ce1a388c.focus()">Leeren</button>
            </label>
        </form>
        <h1>Ergebnisse</h1>
        <table>
            <thead>
                <th>Titel</th>
                <th>komponiert von (u. a.)</th>
                <th>entstanden</th>
                <th>interpretiert von (u. a.)</th>
                <th>Herkunft</th>
                <th>Index</th>
            </thead>
            <tbody>
                <?php foreach ($results as $song):?>
                    <tr>
                        <td><a href="?id=<?php out($song->id)?>"><?php out($song->title)?></a></td>
                        <td><?php out($song->composer)?></td>
                        <td><?php out($song->created_on)?></td>
                        <td><?php out($song->performer)?></td>
                        <td><?php out($song->origin)?></td>
                        <td><?php out($song->index)?></td>
                    </tr>
                <?php endforeach?>
            </tbody>
        </table>
    </body>
</html>
