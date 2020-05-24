<?php

declare(strict_types=1);

namespace TestMonorepo\Monorepo\ReleaseWorker;

use PharIo\Version\Version;
use Symplify\MonorepoBuilder\Release\Contract\ReleaseWorker\ReleaseWorkerInterface;
use Symplify\MonorepoBuilder\Release\Process\ProcessRunner;
use Symplify\MonorepoBuilder\Utils\VersionUtils;

final class PushNextDevReleaseWorker implements ReleaseWorkerInterface
{
    /**
     * @var ProcessRunner
     */
    private $processRunner;

    /**
     * @var VersionUtils
     */
    private $versionUtils;

    private $commitMessage;

    public function __construct(ProcessRunner $processRunner, VersionUtils $versionUtils, string $commitMessage = 'chore: open %s')
    {
        $this->processRunner = $processRunner;
        $this->versionUtils = $versionUtils;
        $this->commitMessage = $commitMessage;
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function work(Version $version): void
    {
        $versionInString = $this->getVersionDev($version);

        $this->processRunner->run(
            sprintf('git add . && git commit --allow-empty -m "'.$this->commitMessage.'" && git push origin HEAD', $versionInString)
        );
    }

    public function getDescription(Version $version): string
    {
        $versionInString = $this->getVersionDev($version);

        return sprintf('Push "%s" open to remote repository ['.$this->commitMessage.']', $versionInString, $versionInString);
    }

    private function getVersionDev(Version $version): string
    {
        return $this->versionUtils->getNextAliasFormat($version);
    }
}
