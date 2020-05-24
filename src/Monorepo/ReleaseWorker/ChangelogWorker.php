<?php

declare(strict_types=1);

namespace TestMonorepo\Monorepo\ReleaseWorker;

use PharIo\Version\Version;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symplify\MonorepoBuilder\Release\Contract\ReleaseWorker\ReleaseWorkerInterface;
use Symplify\MonorepoBuilder\Release\Contract\ReleaseWorker\StageAwareInterface;
use Symplify\MonorepoBuilder\Release\Process\ProcessRunner;
use Symplify\MonorepoBuilder\Utils\VersionUtils;

final class ChangelogWorker implements ReleaseWorkerInterface, StageAwareInterface
{
    /**
     * @var ProcessRunner
     */
    private $processRunner;

    /**
     * @var VersionUtils
     */
    private $versionUtils;

    /** @var string  */
    private $changeLogFileName;

    /** @var string  */
    private $commitMessage;

    public function __construct(ProcessRunner $processRunner, VersionUtils $versionUtils, string $changeLogCommitMessage = 'chore: update changelog for release %s',  string $changeLogFileName = 'CHANGELOG.md')
    {
        $this->processRunner = $processRunner;
        $this->versionUtils = $versionUtils;
        $this->changeLogFileName = $changeLogFileName;
        $this->commitMessage = $changeLogCommitMessage;

        if (!file_exists($this->changeLogFileName)) {
            throw new \Error('Could not find this log file.');
        }
    }

    public function getPriority(): int
    {
        return 1000;
    }

    public function work(Version $version): void
    {
        $finder = new Finder();
        $packages = $finder->in('packages')->depth(0)->directories();

        // Create fake package.json file to trick changelog script.
        $filesystem = new Filesystem();
        $fakePackageJson = $filesystem->tempnam(sys_get_temp_dir(), 'package_');
        dump($fakePackageJson);
        file_put_contents($fakePackageJson, json_encode(['version' => $version->getVersionString()]));

        // Update each package changelog.
        foreach ($packages as $packageFolder) {
            $packageLog = $packageFolder->getPathname().'/'.$this->changeLogFileName;
            $this->processRunner->run('yarn standard-changelog -k '.$fakePackageJson.' --infile='.$packageLog.' --same-file --commit-path='.$packageFolder->getPathname());
        }
    }

    public function getDescription(Version $version): string
    {
        $this->work($version);

        return sprintf('Update packages changelog files for "%s"', $version->getVersionString());
    }

    public function getStage(): string
    {
        return 'changelog';
    }
}
