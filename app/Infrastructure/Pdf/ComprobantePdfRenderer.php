<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

/**
 * Renderiza HTML a PDF con Dompdf bajo una configuracion restringida:
 * sin recursos remotos, sin PHP, sin JavaScript y con chroot minimo.
 */
final class ComprobantePdfRenderer
{
    public function __construct(private readonly string $storagePath)
    {
    }

    public function render(string $html): string
    {
        $dir = rtrim($this->storagePath, "/\\");
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio temporal de PDF: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('El directorio temporal de PDF no es escribible: ' . $dir);
        }

        $options = new Options();
        $options->setChroot($dir);
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setTempDir($dir);
        $options->setFontDir($dir . DIRECTORY_SEPARATOR . 'fonts');
        $options->setFontCache($dir . DIRECTORY_SEPARATOR . 'fonts');
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
