<?php

declare(strict_types=1);

namespace TestMonorepo\Monorepo\ReleaseWorker;

use PharIo\Version\Version;
use Symplify\MonorepoBuilder\Release\Contract\ReleaseWorker\ReleaseWorkerInterface;
use Symplify\MonorepoBuilder\Release\Process\ProcessRunner;
use Throwable;

final class TagVersionReleaseWorker implements ReleaseWorkerInterface
{
    /**
     * @var ProcessRunner
     */
    private $processRunner;

    private $commitMessage;

    public function __construct(ProcessRunner $processRunner, string $prepareReleaseCommitMessage = 'chore: prepare release %s')
    {
        $this->processRunner = $processRunner;
        $this->commitMessage = $prepareReleaseCommitMessage;
    }

    public function getPriority(): int
    {
        return 400;
    }

    public function work(Version $version): void
    {
        $commitMessage = $this->getCommitMessage($version);
        try {
            $this->processRunner->run('git add . && git commit -m "'.$commitMessage.'" && git push origin HEAD');
        } catch (Throwable $throwable) {
            // nothing to commit
        }

        $this->processRunner->run('git tag ' . $version->getVersionString());
    }

    public function getDescription(Version $version): string
    {
        $commitMessage = $this->getCommitMessage($version);
        return sprintf('Commit release changes and add local tag "%s". Commit message: ['.$commitMessage.']', $version->getVersionString());
    }

    protected function getCommitMessage(Version $version): string
    {
        return sprintf($this->commitMessage, $version->getVersionString());
    }
}
