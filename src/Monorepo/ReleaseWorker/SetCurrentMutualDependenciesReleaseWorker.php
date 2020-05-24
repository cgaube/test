<?php

declare(strict_types=1);

namespace TestMonorepo\Monorepo\ReleaseWorker;

use PharIo\Version\Version;
use Symplify\MonorepoBuilder\Release\Contract\ReleaseWorker\StageAwareInterface;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\AbstractMutualDependencyReleaseWorker;

final class SetCurrentMutualDependenciesReleaseWorker extends AbstractMutualDependencyReleaseWorker implements StageAwareInterface
{
    public function getPriority(): int
    {
        return 800;
    }

    public function work(Version $version): void
    {
        $versionInString = $this->versionUtils->getRequiredFormat($version);

        $this->dependencyUpdater->updateFileInfosWithPackagesAndVersion(
            $this->composerJsonProvider->getPackagesFileInfos(),
            $this->packageNamesProvider->provide(),
            $versionInString
        );
    }

    public function getDescription(Version $version): string
    {
        $versionInString = $this->versionUtils->getRequiredFormat($version);

        return sprintf('Set packages mutual dependencies to "%s" version', $versionInString);
    }

    public function getStage(): string
    {
        return "dependencies";
    }
}
