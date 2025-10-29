<?php

declare(strict_types=1);

namespace App\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Logs\LogRecord as OTelLogRecord;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\Contrib\Otlp\LogsExporterFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\LoggerProviderBuilder;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;

/**
 * Monolog handler that forwards logs to an OpenTelemetry Collector via OTLP.
 *
 * It uses the OpenTelemetry PHP Logs SDK and respects standard OTEL_* env vars,
 * e.g. OTEL_EXPORTER_OTLP_ENDPOINT, OTEL_EXPORTER_OTLP_PROTOCOL, etc.
 */
class OtelOtlpHandler extends AbstractProcessingHandler
{
    private $otelLogger;
    private LoggerProviderInterface $provider;

    private static function toNanos(\DateTimeInterface $dt): int
    {
        // seconds to nanoseconds + microseconds to nanoseconds
        return ((int) $dt->format('U')) * 1_000_000_000 + ((int) $dt->format('u')) * 1000;
    }

    public function __construct(Level $level = Level::Info, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        // Create an OTLP logs exporter using environment configuration
        $exporter = new LogsExporterFactory()->create();

        $this->provider = new LoggerProviderBuilder()
            ->addLogRecordProcessor(new BatchLogRecordProcessor($exporter, Clock::getDefault()))
            ->build();

        // Ensure remaining batches are flushed at shutdown
        register_shutdown_function([$this->provider, 'shutdown']);

        $this->otelLogger = $this->provider->getLogger('monolog');
    }

    /**
     * @param LogRecord $record
     */
    protected function write(LogRecord $record): void
    {
        $attributes = [
            'monolog.channel' => $record->channel,
            'monolog.level' => $record->level->getName(),
        ];

        if (!empty($record->context)) {
            foreach ($record->context as $k => $v) {
                $attributes['context.' . $k] = is_scalar($v) ? $v : json_encode($v);
            }
        }
        if (!empty($record->extra)) {
            foreach ($record->extra as $k => $v) {
                $attributes['extra.' . $k] = is_scalar($v) ? $v : json_encode($v);
            }
        }

        $otelRecord = (new OTelLogRecord($record->message))
            ->setSeverityText($record->level->getName())
            ->setSeverityNumber(Severity::fromPsr3(strtolower($record->level->getName())))
            ->setAttributes($attributes)
            ->setObservedTimestamp(self::toNanos($record->datetime));

        $this->otelLogger->emit($otelRecord);
    }
}
