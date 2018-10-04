<?php

declare(strict_types=1);

/**
 * This file is part of Scout Extended.
 *
 * (c) Algolia Team <contact@algolia.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Algolia\ScoutExtended\Settings;

use Illuminate\Filesystem\Filesystem;
use Algolia\AlgoliaSearch\Interfaces\IndexInterface;

/**
 * @internal
 */
class Synchronizer
{
    /**
     * @var \Algolia\ScoutExtended\Settings\Compiler
     */
    private $compiler;

    /**
     * @var \Algolia\ScoutExtended\Settings\Encrypter
     */
    private $encrypter;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    private $files;

    /**
     * @var \Algolia\ScoutExtended\Settings\LocalRepository
     */
    private $localRepository;

    /**
     * @var \Algolia\ScoutExtended\Settings\RemoteRepository
     */
    private $remoteRepository;

    /**
     * Synchronizer constructor.
     *
     * @param \Algolia\ScoutExtended\Settings\Compiler $compiler
     * @param \Algolia\ScoutExtended\Settings\Encrypter $encrypter
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param \Algolia\ScoutExtended\Settings\LocalRepository $localRepository
     * @param \Algolia\ScoutExtended\Settings\RemoteRepository $remoteRepository
     *
     * @return void
     */
    public function __construct(
        Compiler $compiler,
        Encrypter $encrypter,
        Filesystem $files,
        LocalRepository $localRepository,
        RemoteRepository $remoteRepository
    ) {
        $this->compiler = $compiler;
        $this->encrypter = $encrypter;
        $this->localRepository = $localRepository;
        $this->remoteRepository = $remoteRepository;
        $this->files = $files;
    }

    /**
     * Analyses the settings of the given index.
     *
     * @param \Algolia\AlgoliaSearch\Interfaces\IndexInterface $index
     *
     * @return \Algolia\ScoutExtended\Settings\StateResponse
     */
    public function analyse(IndexInterface $index): StateResponse
    {
        $settings = new Settings($this->remoteRepository->from($index), $this->remoteRepository->defaults());

        $path = $this->localRepository->getPath($index->getIndexName());

        return new StateResponse($this->encrypter, $this->files, $settings, $path);
    }

    /**
     * Downloads the settings of the given index.
     *
     * @param \Algolia\AlgoliaSearch\Interfaces\IndexInterface $index
     *
     * @return void
     */
    public function download(IndexInterface $index): void
    {
        $settings = new Settings($this->remoteRepository->from($index), $this->remoteRepository->defaults());

        $path = $this->localRepository->getPath($index->getIndexName());

        $this->compiler->compile($settings, $path);

        $userData = $this->encrypter->local($path);

        $index->setSettings(['userData' => $userData])->wait();
    }

    /**
     * Uploads the settings of the given index.
     *
     * @param \Algolia\AlgoliaSearch\Interfaces\IndexInterface $index
     *
     * @return void
     */
    public function upload(IndexInterface $index): void
    {
        $settings = require $this->localRepository->getPath($index->getIndexName());

        $userData = $this->encrypter->with($settings);

        $index->setSettings(array_merge($settings, ['userData' => $userData]))->wait();
    }
}