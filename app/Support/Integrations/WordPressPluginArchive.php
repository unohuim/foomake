<?php

namespace App\Support\Integrations;

use RuntimeException;
use ZipArchive;

/**
 * Build downloadable WordPress plugin archives from repository-managed source.
 */
class WordPressPluginArchive
{
    /**
     * Build a temporary zip archive for the FooMake WordPress connector.
     */
    public function build(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to build the WordPress plugin archive.');
        }

        $sourcePath = base_path('integrations/wordpress/foomake-connector');

        if (! is_dir($sourcePath)) {
            throw new RuntimeException('The FooMake WordPress plugin source directory is missing.');
        }

        $archivePath = tempnam(sys_get_temp_dir(), 'foomake-wordpress-plugin-');

        if ($archivePath === false) {
            throw new RuntimeException('A temporary archive file could not be created.');
        }

        $zipPath = $archivePath . '.zip';
        rename($archivePath, $zipPath);

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The FooMake WordPress plugin archive could not be opened.');
        }

        $this->addDirectory($zip, $sourcePath, 'foomake-connector');
        $zip->close();

        return $zipPath;
    }

    /**
     * Add a source directory to the archive under the plugin root folder.
     */
    private function addDirectory(ZipArchive $zip, string $sourcePath, string $archiveRoot): void
    {
        $directory = new \RecursiveDirectoryIterator(
            $sourcePath,
            \FilesystemIterator::SKIP_DOTS
        );

        $files = new \RecursiveIteratorIterator(
            $directory,
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = (string) $file->getPathname();
            $relativePath = ltrim(str_replace($sourcePath, '', $filePath), DIRECTORY_SEPARATOR);
            $archivePath = $archiveRoot . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            if ($file->isDir()) {
                $zip->addEmptyDir($archivePath);

                continue;
            }

            $zip->addFile($filePath, $archivePath);
        }
    }
}
