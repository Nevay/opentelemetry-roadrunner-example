<?php declare(strict_types=1);
namespace App;

use Nevay\Otel\Async\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Export\Stream\StreamTransport;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Worker;
use Throwable;
use function Amp\ByteStream\getStdin;
use function Amp\ByteStream\getStdout;
use function sprintf;
use const STDERR;

require __DIR__ . '/../vendor/autoload.php';

$tracerProvider = new TracerProvider(
    new BatchSpanProcessor(new SpanExporter(new StreamTransport(STDERR, 'application/x-ndjson'))),
);

try {
    $worker = new HttpWorker(new Worker(new AmpRelay(getStdin(), getStdout())));
    $tracer = $tracerProvider->getTracer('roadrunner-example');
    while ($request = $worker->waitRequest()) {
        $span = $tracer
            ->spanBuilder(sprintf('HTTP %s', $request->method))
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();
        $scope = $span->activate();

        try {
            $worker->respond(200, 'Hello World!');
        } catch (Throwable $e) {
            $worker->getWorker()->error($e->__toString());
        } finally {
            $scope->detach();
            $span->end();
        }
    }
} finally {
    $tracerProvider->shutdown();
}
