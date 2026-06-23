<?php

declare(strict_types=1);

namespace BankingPipeline\Tests\Shared;

use BankingPipeline\Shared\Envelope;
use BankingPipeline\Shared\FileQueue;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileQueueTest extends TestCase
{
    private string $tempDir;
    private FileQueue $queue;

    protected function setUp(): void
    {
        // Each test gets its own isolated temp directory — never touches the real shared/
        $this->tempDir = sys_get_temp_dir() . '/bptest_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, recursive: true);

        $this->queue = new FileQueue($this->tempDir);
        $this->queue->initialize();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    // -------------------------------------------------------------------------
    // initialize()
    // -------------------------------------------------------------------------

    #[Test]
    public function initializeCreatesAllQueueSubdirectories(): void
    {
        foreach ([FileQueue::DIR_INPUT, FileQueue::DIR_PROCESSING, FileQueue::DIR_OUTPUT, FileQueue::DIR_RESULTS] as $dir) {
            $this->assertDirectoryExists($this->tempDir . DIRECTORY_SEPARATOR . $dir);
        }
    }

    // -------------------------------------------------------------------------
    // write()
    // -------------------------------------------------------------------------

    #[Test]
    public function writeCreatesFileInQueue(): void
    {
        $envelope = $this->makeEnvelope('TXN001');
        $path     = $this->queue->write(FileQueue::DIR_INPUT, $envelope);

        $this->assertFileExists($path);
    }

    #[Test]
    public function writeCreatesFileNamedAfterTransactionId(): void
    {
        $envelope = $this->makeEnvelope('TXN042');
        $this->queue->write(FileQueue::DIR_INPUT, $envelope);

        $files = $this->queue->listFiles(FileQueue::DIR_INPUT);
        $this->assertContains('TXN042.json', $files);
    }

    #[Test]
    public function writeUsesMessageIdWhenTransactionIdAbsent(): void
    {
        $envelope = Envelope::create('src', 'tgt', 'transaction', ['no_txn_id' => true]);
        $this->queue->write(FileQueue::DIR_INPUT, $envelope);

        $files = $this->queue->listFiles(FileQueue::DIR_INPUT);
        $this->assertCount(1, $files);
        // The file should be named after the message_id
        $this->assertStringEndsWith('.json', $files[0]);
    }

    // -------------------------------------------------------------------------
    // read()
    // -------------------------------------------------------------------------

    #[Test]
    public function readParsesEnvelopeFromFile(): void
    {
        $original = $this->makeEnvelope('TXN001');
        $this->queue->write(FileQueue::DIR_INPUT, $original);

        $restored = $this->queue->read('TXN001.json', FileQueue::DIR_INPUT);

        $this->assertSame($original->messageId, $restored->messageId);
        $this->assertSame($original->data, $restored->data);
    }

    #[Test]
    public function readThrowsWhenFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/i');
        $this->queue->read('nonexistent.json', FileQueue::DIR_INPUT);
    }

    // -------------------------------------------------------------------------
    // move()
    // -------------------------------------------------------------------------

    #[Test]
    public function moveTransfersFileAtomically(): void
    {
        $envelope = $this->makeEnvelope('TXN001');
        $this->queue->write(FileQueue::DIR_INPUT, $envelope);

        $newPath = $this->queue->move('TXN001.json', FileQueue::DIR_INPUT, FileQueue::DIR_PROCESSING);

        $this->assertFileExists($newPath);
        $this->assertFileDoesNotExist($this->tempDir . '/input/TXN001.json');
        $this->assertFileExists($this->tempDir . '/processing/TXN001.json');
    }

    #[Test]
    public function moveThrowsWhenSourceFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/i');
        $this->queue->move('ghost.json', FileQueue::DIR_INPUT, FileQueue::DIR_PROCESSING);
    }

    #[Test]
    public function movePreservesFileContent(): void
    {
        $original = $this->makeEnvelope('TXN002');
        $this->queue->write(FileQueue::DIR_INPUT, $original);
        $this->queue->move('TXN002.json', FileQueue::DIR_INPUT, FileQueue::DIR_PROCESSING);

        $restored = $this->queue->read('TXN002.json', FileQueue::DIR_PROCESSING);
        $this->assertSame($original->messageId, $restored->messageId);
    }

    // -------------------------------------------------------------------------
    // Full pipeline flow: input → processing → output → results
    // -------------------------------------------------------------------------

    #[Test]
    public function fullPipelineFlowMovesFileToResults(): void
    {
        $envelope = $this->makeEnvelope('TXN099');
        $this->queue->write(FileQueue::DIR_INPUT, $envelope);

        $this->queue->move('TXN099.json', FileQueue::DIR_INPUT,      FileQueue::DIR_PROCESSING);
        $this->queue->move('TXN099.json', FileQueue::DIR_PROCESSING, FileQueue::DIR_OUTPUT);
        $this->queue->move('TXN099.json', FileQueue::DIR_OUTPUT,     FileQueue::DIR_RESULTS);

        $files = $this->queue->listFiles(FileQueue::DIR_RESULTS);
        $this->assertContains('TXN099.json', $files);
        $this->assertEmpty($this->queue->listFiles(FileQueue::DIR_INPUT));
        $this->assertEmpty($this->queue->listFiles(FileQueue::DIR_PROCESSING));
        $this->assertEmpty($this->queue->listFiles(FileQueue::DIR_OUTPUT));
    }

    // -------------------------------------------------------------------------
    // Invalid directory name
    // -------------------------------------------------------------------------

    #[Test]
    public function writeThrowsOnInvalidDirectoryName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/invalid queue directory/i');
        $this->queue->write('nonexistent_dir', $this->makeEnvelope('X'));
    }

    #[Test]
    public function moveThrowsOnInvalidSourceDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->queue->move('some.json', 'bad_dir', FileQueue::DIR_PROCESSING);
    }

    #[Test]
    public function readThrowsOnInvalidDirectoryName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->queue->read('some.json', 'bad_dir');
    }

    // -------------------------------------------------------------------------
    // listFiles() and clear()
    // -------------------------------------------------------------------------

    #[Test]
    public function listFilesReturnsEmptyArrayWhenQueueIsEmpty(): void
    {
        $this->assertSame([], $this->queue->listFiles(FileQueue::DIR_INPUT));
    }

    #[Test]
    public function clearRemovesAllFilesFromDirectory(): void
    {
        $this->queue->write(FileQueue::DIR_INPUT, $this->makeEnvelope('TXN001'));
        $this->queue->write(FileQueue::DIR_INPUT, $this->makeEnvelope('TXN002'));
        $this->queue->clear(FileQueue::DIR_INPUT);

        $this->assertEmpty($this->queue->listFiles(FileQueue::DIR_INPUT));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeEnvelope(string $transactionId): Envelope
    {
        return Envelope::create(
            source: 'test',
            target: 'test',
            type: 'transaction',
            data: ['transaction_id' => $transactionId, 'amount' => '100.00'],
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
