<?php
/**
 * Stahovač detailů o bytových jednotkách z Katastru
 * =================================================
 *
 * Script stáhne ze systému Nahlížení do katastru nemovitostí informace o jednotkách a připraví data pro import
 * do Prezenční listiny projektu Hlasovacího listu.
 *
 * Použití:
 * --------
 * V příkazové řádce zavolejte script, např.:
 *      php katastr.php
 *
 * Parametry:
 *  -u <url>
 *  --url <url>
 *          URL na stránku Informace o stavbě v aplikaci Nahlížení do katastru.
 *          Volitelné, v případě nezadání musí být soubor `stavba.html` již stažen.
 *
 *  -d <dir>
 *  --dir <dir>
 *          Cesta k adresáři, kam se budou ukládat výstupy.
 *          Volitelné, jinak se použije aktuální adresář.
 *          Pokud není uveden <url> parametr, tak v cestě musí být uložen soubor `stavba.html`.
 *
 * Výstup scriptu
 * --------------
 * Script po úspěšném provedení do adresáře <dir> uloží následující soubory:
 *  - HTML
 *          HTML soubory obsahující přesnou podovu dat získaných z katastru.
 *          Soubor `stavba.html` je stránkou Informace o stavbě.
 *          Soubory `jednotka-*.html` jsou stránkou Informace o jednotce.
 *
 *  - JSON
 *          Soubor `stavba.json` obsahuje výstup parsování HTML souboru `stavba.html`.
 *          Soubor `jednotky.json` obsahuje výstup parsování HTML souborů `jednotka-*.html`.
 *
 *  - CSV
 *          Soubor `jednotky.csv` obsahuje seznam vlastníků použitelný pro Prezenční listina projektu Hlasovací list.
 *
 * Získání URL na stránku Informace o stavbě v aplikaci Nahlížení do katastru nemovitostí
 * --------------------------------------------------------------------------------------
 * 1) Jděte na stránku https://nahlizenidokn.cuzk.cz/
 * 2) Zvolte Vyhledat stavbu.
 * 3) Najděte požadovanou stavbu.
 * 4) Zkopírujte URL stránky Informace o stavbě
 * POZOR: URL platí jen nějakolik minut!
 *
 * Příklad použití
 * ---------------
 *      php katastr.php -d ./katastr/ -u "https://nahlizenidokn.cuzk.cz/ZobrazObjekt.aspx?encrypted=EGIHySaN...XiiA=="
 *
 * @author Jakub Bouček <pan@jakubboucek.cz>
 * @link https://github.com/jakubboucek/google-script-svj-hlasovaci-list
 * @license MIT
 * @copyright Copyright (c) 2020, Jakub Bouček
 * @link https://www.jakub-boucek.cz/
 */

// Prevent call as wep app
if (PHP_SAPI !== 'cli') {
    http_response_code(400);
    echo 'ERROR: Tool is callable only from command-line' . PHP_EOL;
    die(1);
}

// Handle exceptions
set_exception_handler(
    static function (\Throwable $e): void {
        fwrite(
            STDERR,
            sprintf(
                "\n\033[31m%s\033[0m\n",
                sprintf(
                    "%1\$s in %3\$s:%4\$d\n%2\$s",
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                )
            )
        );
        $code = $e->getCode();
        die(($code > 0 && $code < 255) ? $code : 1);
    }
);

// Parse CLI options
$opts = getopt('u:d:', ['url:', 'dir:']);
$url = $opts['u'] ?? $opts['url'] ?? null;
$dir = getcwd();
$path = $opts['d'] ?? $opts['dir'] ?? null;
if ($path !== null) {
    if (strpos($path, '/') === 0) {
        $dir = $path;
    } else {
        $dir = sprintf("%s/%s", $dir, ltrim($path, '/'));
    }
}
$newdir = realpath($dir);
if ($newdir === false) {
    throw new LogicException("Adresář '$dir' neexistuje");
}
$dir = $newdir;
unset($newdir, $path);

// Download info about building
if ($url) {
    if (preg_match('~^https://nahlizenidokn\.cuzk\.cz/~', $url) !== 1) {
        throw new LogicException("URL '$url' není pro tento nástroj povolena");
    }
    $fileName = $dir . '/stavba.html';
    echo "Stahuji informace o stavbě... ";
    if (file_exists($fileName) === false) {
        $file = file_get_contents($url);
        if ($file === false) {
            throw new RuntimeException("Nelze načíst URL '$url'");
        }
        file_put_contents($fileName, $file);
        echo "OK\n";
    } else {
        echo "přeskakuji, již staženo.\n";
    }

    $buildingMeta = [];
    $file = file_get_contents($fileName);
    $dom = (new DOMDocument());
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $file, LIBXML_NOBLANKS | LIBXML_NOERROR);
    $xpath = new DomXPath($dom);

    echo "Hledám atirbuty parcely... ";
    $nodes = $xpath->query("//table[@summary='Atributy parcely']//tr");
    $attributes = [];
    foreach ($nodes as $node) {
        $tds = $xpath->query("td", $node);
        $attributes[$tds[0]->nodeValue] = $tds[1]->nodeValue;
    }
    echo sprintf("nalezeno %d atributů.\n", count($attributes));
    $buildingMeta['parcela'] = $attributes;

    echo "Hledám atirbuty stavby... ";
    $nodes = $xpath->query("//table[@summary='Atributy stavby']//tr");
    $attributes = [];
    foreach ($nodes as $node) {
        $tds = $xpath->query("td", $node);
        $attributes[$tds[0]->nodeValue] = $tds[1]->nodeValue;
    }
    echo sprintf("nalezeno %d atributů.\n", count($attributes));
    $buildingMeta['stavba'] = $attributes;


    echo "Hledám jednotky stavby... ";
    $nodes = $xpath->query("//table[@summary='Vymezené jednotky']//a");

    $links = [];
    foreach ($nodes as $node) {
        $links[$node->nodeValue] = $node->attributes->getNamedItem("href")->nodeValue;
    }
    echo sprintf("nalezeno %d jednotek.\n", count($links));

    $files = [];
    foreach ($links as $name => $link) {
        $files[$name] = sprintf('jednotka-%s.html', str_replace('/', '-', $name));
    }
    $buildingMeta['jednotky'] = $files;
    $fileName = $dir . '/stavba.json';
    file_put_contents(
        $fileName,
        json_encode(
            $buildingMeta,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        )
    );
    echo "Metadata stavby uloženy do souboru $fileName.\n";

    echo "Stahuji informace o jednotkách:\n";
    $baseUrl = preg_replace('~(.+://.+?/).+~', '$1', $url);

    foreach ($links as $name => $link) {
        $fileName = $dir . '/' . $files[$name];
        echo "  - $name... ";
        if (file_exists($fileName) === false) {
            $link = $baseUrl . $link;
            $file = file_get_contents($link);
            if ($file === false) {
                throw new RuntimeException("Nelze načíst URL '$link'");
            }
            file_put_contents($fileName, $file);
            echo "OK\n";
        } else {
            echo "přeskakuji, již staženo.\n";
        }
    }
}


// Parse fields info
$fileName = $dir . '/stavba.json';
if (file_exists($fileName) === false) {
    throw new LogicException(
        "Soubor '$fileName' neexistuje, je potřeba nejdříve stahnout informace o stavbě přidáním parametru --url"
    );
}
$buildingMeta = json_decode(file_get_contents($fileName), true, 512, JSON_THROW_ON_ERROR);
echo "Zpracovávám informace o jednotách:\n";

$fields = [];
foreach ($buildingMeta['jednotky'] as $name => $file) {
    echo "  - $name... ";
    $file = $dir . '/' . $file;

    $fileContent = file_get_contents($file);
    $dom = (new DOMDocument());
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $fileContent, LIBXML_NOBLANKS | LIBXML_NOERROR);

    $xpath = new DomXPath($dom);
    $nodes = $xpath->query("//table[@summary='Atributy jednotky']//tr");
    $attributes = [];
    foreach ($nodes as $node) {
        $tds = $xpath->query("td", $node);
        $attributes[str_replace('naspolečných', 'na společných', $tds[0]->nodeValue)] = $tds[1]->nodeValue;
    }

    $nodes = $xpath->query("//table[@summary='Vlastníci, jiní oprávnění']//tr");
    $owners = [];
    foreach ($nodes as $node) {
        $tds = $xpath->query("td", $node);
        if ($tds->length === 0) {
            continue;
        }

        if ($tds->length === 1) {
            $owners[array_key_last($owners)]['partneri'][] = $tds[0]->nodeValue;
            continue;
        }

        $owners[] = [
            'jmeno' => $tds[0]->nodeValue,
            'pomer' => $tds[1]->nodeValue,
            'partneri' => []
        ];
    }

    $fields[$name] = [
        'atributy' => $attributes,
        'vlastnici' => $owners
    ];
    echo "OK\n";
}

$fileName = $dir . '/jednotky.json';
file_put_contents(
    $fileName,
    json_encode(
        $fields,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
    )
);
echo "Metadata jednotek uloženy do souboru $fileName.\n";

// Build CSV
$fileName = $dir . '/jednotky.csv';
$file = fopen($fileName, 'wb');

fputcsv($file, ['Jednotka', 'Seznam vlastníků', 'Zmocněnec', 'Čitatel', 'Jmenovatel']);

foreach ($fields as $name => $field) {
    $owners = [];
    $agents = [];
    foreach ($field['vlastnici'] as $owner) {
        $ownerText = $owner['jmeno'];
        if ($owner['partneri']) {
            $ownerText .= sprintf(' (%s)', implode('; ', $owner['partneri']));
        }
        $owners[] = $ownerText;

        if (preg_match('/^SJM (.+?) a (.+?),/', $owner['jmeno'], $m)) {
            $agents[] = $m[1];
            $agents[] = $m[2];
        } else {
            $agents[] = strtok($owner['jmeno'], ',');
        }
    }
    $owners = implode("\n", $owners);
    $agents = implode(" / ", $agents);

    $ratio = explode('/', $field['atributy']['Podíl na společných částech:']);

    fputcsv($file, [$name, $owners, $agents, $ratio[0], $ratio[1]]);
}
fclose($file);
echo "Metadata jednotek uloženy také do souboru $fileName.\n";
