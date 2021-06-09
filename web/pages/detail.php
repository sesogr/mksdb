<?php // Die folgende Deklaration ermöglicht Code-Unterstützung und -vervollständigung für $song und Unterobjekte ?>
<?php /** @var $song Song */?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Schlagerdatenbank Detailansicht</title>
    </head>
    <body>
        <h1>Schlagerdatenbank Detailansicht</h1>
        <p><a href="?">neue Suche</a></p>
        <table>
            <tbody>
                <?php /*
                    out(string) mit einem Parameter gibt diesen HTML-sicher aus, d. h. HTML-Markup wird escaped
                    out(template, ...strings) mit zwei oder mehr Parametern funktioniert wie sprintf() und gibt das Ergebnis HTML-sicher aus
                */?>
                <tr><th>ID</th><td><?php out($song->id)?></td></tr>
                <tr><th>Titel</th><td><?php out($song->name)?></td></tr>
                <tr><th>Komponiert von</th><td>
                    <ul>
                        <?php foreach($song->composers as $composer):?>
                            <?php /*
                                Die Liste $song->composers und auch andere Listen kommen bereits sortiert nach der Position an.
                                Auf diese Position kann mit z. B. $composer->position zugegriffen werden.
                                In der Annotation finden sich die „Unsicherheits“-markierungen wie z. B. „[?]“ oder „??“, welche
                                beim Datenimport automatisch vom Ende jeder Datenzelle abgetrennt worden sind.
                            */?>
                            <li><?php out('%s%s', $composer->person->name, $composer->annotation)?></li>
                        <?php endforeach?>
                    </ul>
                </td></tr>
                <tr><th>Text von</th><td>
                    <ul>
                        <?php foreach($song->writers as $writer):?>
                            <li><?php out('%s%s', $writer->person->name, $writer->annotation)?></li>
                        <?php endforeach?>
                    </ul>
                </td></tr>
                <tr><th>Copyright</th><td><?php out($song->copyright_year)?></td></tr>
                <?php if ($song->copyright_remark):?><tr><th>Copyrightvermerk</th><td><?php out($song->copyright_remark)?></td></tr><?php endif?>
                <tr><th>Entstehung</th><td><?php out($song->created_on)?></td></tr>
                <?php /*
                    In der Ursprungsdatei gab es nur eine „Graphiker“-Spalte, aber die DB ist bereits auf möglicherweise mehrere ausgelegt.
                    Sobald mehrere gepflegt werden, sollte hier foreach benutzt werden, statt auf Index 0 zuzugreifen.
                    Auch nachfolgend gibt es mehrere Unterobjekte, die bisher nur einzeln auftreten und daher per Index 0 angesprochen werden
                */?>
                <tr><th>Graphik von</th><td><?php out('%s%s', $song->coverArtists[0]->person->name, $song->coverArtists[0]->annotation)?></td></tr>
                <tr><th>Interpretiert von</th><td>
                    <ul>
                        <?php foreach($song->performers as $performer):?>
                            <li><?php out('%s%s', $performer->person->name, $performer->annotation)?></li>
                        <?php endforeach?>
                    </ul>
                </td></tr>
                <tr><th>Label</th><td><?php out($song->label)?></td></tr>
                <tr><th>Verlag</th><td><?php out('%s%s', $song->publishers[0]->publisher->name, $song->publishers[0]->annotation)?></td></tr>
                <tr><th>Verlagsort</th><td><?php out('%s%s', $song->publicationPlaces[0]->city->name, $song->publicationPlaces[0]->annotation)?></td></tr>
                <tr><th>Verlagsreihe</th><td><?php out($song->publisher_series)?></td></tr>
                <tr><th>Verlagsnummer</th><td><?php out($song->publisher_number)?></td></tr>
                <tr><th>Plattennr.</th><td><?php out($song->record_number)?></td></tr>
                <tr><th>Herkunft</th><td><?php out($song->origin)?></td></tr>
                <tr><th>Gattung</th><td><?php out('%s%s', $song->genres[0]->genre->name, $song->genres[0]->annotation)?></td></tr>
                <tr><th>Widmung</th><td><?php out($song->dedication)?></td></tr>
                <tr><th>Sammlungen</th><td><?php out('%s%s', $song->collections[0]->collection->name, $song->collections[0]->annotation)?></td></tr>
                <tr><th>Kritik</th><td><?php out($song->review)?></td></tr>
                <tr><th>Ergänzung</th><td><?php out($song->addition)?></td></tr>
                <tr><th>Quelle</th><td><?php out('%s%s', $song->sources[0]->source->name, $song->sources[0]->annotation)?></td></tr>
                <tr><th>Index</th><td><?php out($song->index_no)?></td></tr>
            </tbody>
        </table>
    </body>
</html>
