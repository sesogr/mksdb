<?php declare(strict_types=1);
/** @var SplFileInfo $fileInfo */
$buildDir = __DIR__ . '/../app-dist/';
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS)) as $fileInfo) {
    $fileName = str_replace($buildDir, '', $fileInfo->getPathname());
    $files[$fileName] = base64_encode(gzcompress(file_get_contents($fileInfo->getPathname())));
}
[$code] = explode('__halt_compiler();', file_get_contents(__DIR__ . '/../htdocs-installer/install.php'));
file_put_contents(
    __DIR__ . '/../htdocs-installer/install.php',
    sprintf(
        "%s__halt_compiler();\n%s\n\n%s\n",
        $code,
        implode(
            "\n",
            array_map(
                function ($name) use ($files) {
                    return sprintf("%s:%s", $name, strlen($files[$name]));
                },
                array_keys($files)
            )
        ),
        implode("\n", $files)
    )
);
