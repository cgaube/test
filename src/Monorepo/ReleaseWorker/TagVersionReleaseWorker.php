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

    public function __construct(ProcessRunner $processRunner, string $prepareReleaseCommitMessage = 'chore: prepare release')
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
        try {
            $this->processRunner->run('git add . && git commit -m "'.$this->commitMessage.'" && git push origin master');
        } catch (Throwable $throwable) {
            // nothing to commit
        }

        $this->processRunner->run('git tag ' . $version->getVersionString());
    }

    public function getDescription(Version $version): string
    {
        return sprintf('Add local tag "%s" ['.$this->commitMessage.']', $version->getVersionString());
    }
}
