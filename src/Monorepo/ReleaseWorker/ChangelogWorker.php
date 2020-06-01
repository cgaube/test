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

        // Create version json file to for the changelog script.
        $filesystem = new Filesystem();
        $versionFile = $filesystem->tempnam(sys_get_temp_dir(), 'package_');

        $filesystem->dumpFile($versionFile, json_encode(['version' => $version->getVersionString()]));

        $paths = [
            './',
        ];
        foreach ($packages as $packageFolder) {
            $paths[] = $packageFolder->getPathname();
        }

        // Update each package changelog.
        foreach ($paths as $path) {
            $logFile = $path.'/'.$this->changeLogFileName;

            $fakeOutput = $filesystem->tempnam(sys_get_temp_dir(), 'output_a');

            $this->processRunner->run('yarn standard-changelog --pkg '.$versionFile.' --infile='.$logFile.' --outfile='.$fakeOutput.' --commit-path='.$path);

            $packageChangeLog = $this->cleanLogs(
                file_get_contents($fakeOutput),
                $version->getPatch()->getValue() == 0
            );

            $filesystem->dumpFile($logFile, $packageChangeLog);

            // Make sure we have some changes in this package
            $filesystem->remove($fakeOutput);
        }

        $filesystem->remove($versionFile);
    }

    public function getDescription(Version $version): string
    {
        return sprintf('Update packages changelog files for "%s"', $version->getVersionString());
    }

    public function getStage(): string
    {
        return 'changelog';
    }

    protected function cleanLogs($log, $addNoChangesText = false) {

        $log = trim($log);

        $hasChanges = count(explode(PHP_EOL, $log)) > 1;

        if (!$hasChanges && $addNoChangesText) {
            $log .= str_repeat(PHP_EOL, 2) .'* No changes';
        }

        // Remove double lines.
        $log = preg_replace('/\n{3,}/', PHP_EOL.PHP_EOL, $log);

        $log .= PHP_EOL;
        return $log;
    }
}
