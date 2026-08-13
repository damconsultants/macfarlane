<?php
namespace DamConsultants\Macfarlane\Logger;

use Magento\Framework\Filesystem\Driver\File;

class Logger
{
    private string $fileName;

    public function __construct(
        private readonly File $filesystem,
        string $cronName
    ) {
        $date = date('Y-m-d');
        $directory = BP . '/var/log/macfarlane';

        if (!$this->filesystem->isExists($directory)) {
            $this->filesystem->createDirectory($directory, 0775);
        }

        $this->fileName = $directory . '/' . $cronName . '-' . $date . '.log';
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $line = '[' . $timestamp . '] ' . $level . ': ' . $message . PHP_EOL;

        $this->filesystem->filePutContents(
            $this->fileName,
            $line,
            FILE_APPEND
        );
    }
}
