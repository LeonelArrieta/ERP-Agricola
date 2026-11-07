<?php

declare(strict_types=1);

namespace App\Infrastructure\Qr;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Representacion grafica del QR como SVG (sin dependencia de ext-gd).
 */
final class QrSvgRenderer
{
    public function render(string $payload): string
    {
        $options = new QROptions();
        $options->outputBase64 = false;
        $options->svgAddXmlHeader = false;
        $options->drawLightModules = false;

        return (string) (new QRCode($options))->render($payload);
    }
}
