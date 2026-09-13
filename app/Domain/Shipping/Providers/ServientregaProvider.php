<?php

namespace App\Domain\Shipping\Providers;

use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\DTOs\ShippingGuide;
use App\Domain\Shipping\DTOs\ShippingGuideRequest;
use App\Domain\Shipping\DTOs\ShippingQuote;
use App\Domain\Shipping\DTOs\ShippingQuoteRequest;
use App\Domain\Shipping\Exceptions\ShippingProviderException;
use App\Enums\Integration;
use App\Enums\ShippingProvider;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/**
 * Servientrega Ecuador. `quote()` porta `calculatePrice` (SOAP del cotizador)
 * y `createGuide()` porta `servientrega-guide.ts` (REST `guiawebs`, el
 * endpoint que sí corre en producción). Credenciales y remitente en config.
 * Diferencias con Node: se verifica TLS, y un fallo de cotización se registra
 * antes de aplicar el costo de respaldo en vez de tragarse en silencio.
 */
final class ServientregaProvider implements ShippingProviderContract
{
    public function provider(): ShippingProvider
    {
        return ShippingProvider::Servientrega;
    }

    public function quote(ShippingQuoteRequest $request): ShippingQuote
    {
        $config = config('shineray.shipping');
        $weight = max((float) $config['min_weight_kg'], $request->totalWeightKg);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml;charset=UTF-8',
                'SOAPAction' => rtrim((string) config('services.servientrega.quote_url'), '/').'/Consultar',
            ])
                ->connectTimeout(3)
                ->timeout((int) $config['quote']['timeout_seconds'])
                ->withBody($this->quoteEnvelope($request, $weight), 'text/xml')
                ->post((string) config('services.servientrega.quote_url'))
                ->throw();

            $fleteCents = $this->parseQuoteFlete($response->body());
        } catch (Throwable $exception) {
            IntegrationLog::record(Integration::Servientrega, 'quote_fallback', [
                'destination' => $request->destinationCity.'-'.$request->destinationProvince,
                'weight_kg' => $weight,
                'message' => $exception->getMessage(),
            ]);

            return new ShippingQuote((int) $config['quote']['fallback_cost_cents'], isFallback: true, raw: ['error' => $exception->getMessage()]);
        }

        return new ShippingQuote($fleteCents, isFallback: false, raw: ['flete_cents' => $fleteCents, 'weight_kg' => $weight]);
    }

    public function createGuide(ShippingGuideRequest $request): ShippingGuide
    {
        $config = config('shineray.shipping');
        $sender = $config['sender'];
        $guide = $config['guide'];
        $weight = max((float) $config['min_weight_kg'], $request->totalWeightKg);

        $payload = [
            'id_tipo_logistica' => $guide['id_tipo_logistica'],
            'detalle_envio_1' => '',
            'detalle_envio_2' => '',
            'detalle_envio_3' => '',
            'id_ciudad_origen' => $guide['id_ciudad_origen'],
            'id_ciudad_destino' => (int) $request->destinationCityId,
            'id_destinatario_ne_cl' => $request->recipientDni,
            'razon_social_desti_ne' => $guide['razon_social_destinatario'],
            'nombre_destinatario_ne' => $request->recipientName,
            'apellido_destinatar_ne' => $request->recipientLastName,
            'direccion1_destinat_ne' => $request->recipientAddress,
            'sector_destinat_ne' => '',
            'telefono1_destinat_ne' => $request->recipientPhone,
            'telefono2_destinat_ne' => '',
            'codigo_postal_dest_ne' => '',
            'id_remitente_cl' => $sender['id'],
            'razon_social_remite' => $sender['business_name'],
            'nombre_remitente' => $sender['first_name'],
            'apellido_remite' => $sender['last_name'],
            'direccion1_remite' => $sender['address'],
            'sector_remite' => '',
            'telefono1_remite' => $sender['phone'],
            'telefono2_remite' => '',
            'codigo_postal_remi' => '',
            'id_producto' => $guide['id_producto'],
            'contenido' => $guide['contenido'],
            'numero_piezas' => 1,
            'valor_mercancia' => number_format($request->declaredValueCents / 100, 2, '.', ''),
            'valor_asegurado' => 0,
            'largo' => 1,
            'ancho' => 1,
            'alto' => 1,
            'peso_fisico' => round($weight / (float) $guide['weight_divisor'], 2),
            'login_creacion' => (string) config('services.servientrega.guide_login'),
            'password' => (string) config('services.servientrega.guide_password'),
        ];

        try {
            $body = (array) Http::acceptJson()
                ->connectTimeout(5)
                ->timeout((int) config('services.servientrega.timeout', 30))
                ->post((string) config('services.servientrega.guide_url'), $payload)
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw new ShippingProviderException('Servientrega no pudo crear la guía: '.$exception->getMessage(), 0, $exception);
        }

        $guideNumber = trim((string) ($body['id'] ?? ''));

        if ($guideNumber === '' || $guideNumber === '0') {
            throw new ShippingProviderException('Servientrega respondió sin número de guía: '.json_encode($body));
        }

        return new ShippingGuide($guideNumber, raw: $body);
    }

    public function cancelGuide(string $guideNumber): void
    {
        // Hoy no existe anulación en el flujo actual; pendiente confirmar con Servientrega.
        throw new ShippingProviderException('La anulación de guías no está implementada.');
    }

    private function quoteEnvelope(ShippingQuoteRequest $request, float $weight): string
    {
        $config = config('shineray.shipping');
        $destination = htmlspecialchars($request->destinationCity.'-'.$request->destinationProvince, ENT_XML1);
        $value = number_format($request->declaredValueCents / 100, 2, '.', '');

        return <<<XML
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <ConsultarRequest>
      <producto>{$config['quote']['product']}</producto>
      <origen>{$config['origin_city']}</origen>
      <destino>{$destination}</destino>
      <valor_mercaderia>{$value}</valor_mercaderia>
      <piezas>1</piezas>
      <peso>{$weight}</peso>
      <alto>1</alto>
      <ancho>1</ancho>
      <largo>1</largo>
      <tokn>{$this->xml(config('services.servientrega.quote_token'))}</tokn>
      <usu>{$this->xml(config('services.servientrega.quote_user'))}</usu>
      <pwd>{$this->xml(config('services.servientrega.quote_password'))}</pwd>
    </ConsultarRequest>
  </soap:Body>
</soap:Envelope>
XML;
    }

    /**
     * La respuesta SOAP trae un XML escapado dentro de `<Result>`; el flete va
     * en `ConsultarResult/flete`, en dólares.
     */
    private function parseQuoteFlete(string $body): int
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $envelope = new SimpleXMLElement($body);
            $result = $envelope->xpath('//*[local-name()="Result"]')[0] ?? null;

            if ($result === null) {
                $fault = $envelope->xpath('//*[local-name()="faultstring"]')[0] ?? null;

                throw new ShippingProviderException('Cotización sin resultado: '.($fault !== null ? (string) $fault : 'respuesta inesperada'));
            }

            $inner = new SimpleXMLElement(html_entity_decode(trim((string) $result), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $flete = $inner->xpath('//flete')[0] ?? null;

            if ($flete === null || ! is_numeric((string) $flete)) {
                throw new ShippingProviderException('Cotización sin flete numérico.');
            }

            return (int) round(round((float) $flete, 2) * 100);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function xml(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1);
    }
}
