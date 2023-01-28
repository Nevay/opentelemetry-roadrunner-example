<?php declare(strict_types=1);
namespace App;

use Amp\ByteStream\BufferedReader;
use Amp\ByteStream\BufferException;
use Amp\ByteStream\ClosedException;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableStream;
use Spiral\Goridge\Exception\HeaderException;
use Spiral\Goridge\Exception\TransportException;
use Spiral\Goridge\Frame;
use Spiral\Goridge\Relay;

final class AmpRelay extends Relay {

    private readonly BufferedReader $in;
    private readonly WritableStream $out;

    public function __construct(ReadableStream $in, WritableStream $out) {
        $this->in = new BufferedReader($in);
        $this->out = $out;
    }

    public function waitFrame(): Frame {
        try {
            $header = $this->in->readLength(12);
        } catch (StreamException | BufferException $e) {
            throw new HeaderException('Unable to read frame header: ' . $e->getMessage(), previous: $e);
        }

        /** @noinspection PhpInternalEntityUsedInspection */
        $parts = Frame::readHeader($header);
        $length = $parts[1] * 4 + $parts[2];
        try {
            $payload = $this->in->readLength($length);
        } catch (StreamException | BufferException $e) {
            throw new TransportException('Unable to read payload: ' . $e->getMessage(), previous: $e);
        }

        /** @noinspection PhpInternalEntityUsedInspection */
        return Frame::initFrame($parts, $payload);
    }

    public function send(Frame $frame): void {
        /** @noinspection PhpInternalEntityUsedInspection */
        $body = Frame::packFrame($frame);

        try {
            $this->out->write($body);
        } catch (StreamException | ClosedException $e) {
            throw new TransportException('Unable to write payload: ' . $e->getMessage(), previous: $e);
        }
    }
}
