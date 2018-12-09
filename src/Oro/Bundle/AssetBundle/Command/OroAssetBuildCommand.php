<?php

namespace Oro\Bundle\AssetBundle\Command;

use Oro\Bundle\AssetBundle\Cache\BundlesPathCache;
use Oro\Bundle\AssetBundle\NodeProcessFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Run bin/webpack to build assets.
 */
class OroAssetBuildCommand extends Command
{
    protected static $defaultName = 'oro:assets:build';

    protected const BUILD_DIR = './vendor/oro/platform/build/';

    /**
     * @var NodeProcessFactory
     */
    private $nodeProcessFactory;

    /**
     * @var BundlesPathCache
     */
    private $cache;

    /**
     * @var string
     */
    private $npmPath;

    /**
     * @var int|float|null
     */
    private $buildTimeout;

    /**
     * @var int|float|null
     */
    private $npmInstallTimeout;

    /**
     * @param NodeProcessFactory $nodeProcessFactory
     * @param BundlesPathCache   $cache
     * @param string             $npmPath
     * @param int|float|null     $buildTimeout
     * @param int|float|null     $npmInstallTimeout
     */
    public function __construct(
        NodeProcessFactory $nodeProcessFactory,
        BundlesPathCache $cache,
        string $npmPath,
        $buildTimeout,
        $npmInstallTimeout
    ) {
        $this->nodeProcessFactory = $nodeProcessFactory;
        $this->cache = $cache;
        $this->npmPath = $npmPath;
        $this->buildTimeout = $buildTimeout;
        $this->npmInstallTimeout = $npmInstallTimeout;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Run bin/webpack to build assets')
            ->addArgument(
                'theme',
                InputArgument::OPTIONAL,
                'Theme name to build. When not provided - all available themes will be built.'
            )
            ->addOption(
                'watch',
                'w',
                InputOption::VALUE_NONE,
                'Turn on watch mode. This means that after the initial build, 
                webpack will continue to watch for changes in any of the resolved files.'
            )
            ->addOption(
                'npm-install',
                'i',
                InputOption::VALUE_NONE,
                'Reinstall npm dependencies to vendor/oro/platform/build folder, to be used by webpack.'.
                'Required when "node_modules" folder is corrupted.'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $kernel = $this->getKernel();
        $io = new SymfonyStyle($input, $output);

        if (!$this->cache->exists($kernel->getCacheDir())) {
            $io->text('<info>Warming up the bundles.json cache.</info>');
            $this->cache->warmUp($kernel->getCacheDir());
            $io->text('Done');
        }

        $nodeModulesDir = $kernel->getProjectDir().'/'.self::BUILD_DIR.'node_modules';
        if (!file_exists($nodeModulesDir) || $input->getOption('npm-install')) {
            $output->writeln('<info>Installing npm dependencies.</info>');
            $this->npmInstall($output);
        }

        $output->writeln('<info>Building assets.</info>');
        $this->buildAssets($input, $output);
        $io->success('All assets were successfully build.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function buildAssets(InputInterface $input, OutputInterface $output): void
    {
        $command = 'vendor/oro/platform/build/node_modules/.bin/webpack';

        if ($input->getArgument('theme')) {
            $command .= ' --env.theme='.$input->getArgument('theme');
        }
        if (true === $input->getOption('no-debug') || 'prod' === $input->getOption('env')) {
            $command .= ' --mode=production';
        }
        if ($input->getOption('watch')) {
            $command .= ' --watch';
        }
        $command .= ' --env.symfony='.$input->getOption('env');


        $process = $this->nodeProcessFactory->createProcess(
            $command,
            $this->getKernel()->getProjectDir(),
            $this->buildTimeout
        );
        $this->enableTty($process);

        if ($input->getOption('watch')) {
            $process->setTimeout(null);
        }

        $process->run(
            function ($type, $buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }

    /**
     * @param OutputInterface $output
     */
    protected function npmInstall(OutputInterface $output): void
    {
        $command = $this->npmPath.' --prefix '.self::BUILD_DIR.' --no-audit install '.self::BUILD_DIR;

        $process = new Process($command, $this->getKernel()->getProjectDir());
        $process->setTimeout($this->npmInstallTimeout);

        $process->run();

        if ($process->isSuccessful()) {
            $output->writeln('Done.');
        } else {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }

    /**
     * @return Kernel
     */
    private function getKernel(): Kernel
    {
        return $this->getApplication()->getKernel();
    }

    /**
     * @param Process $process
     */
    protected function enableTty(Process $process): void
    {
        try {
            $process->setTty(true);
        } catch (RuntimeException $exception) {
            $process->setTty(false);
        }
    }
}
