<?php

namespace Sitewards\StoreStructure\Console\Command;

use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;
use \Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use \Magento\Store\Model\StoreManagerInterface;
use \Symfony\Component\Console\Input\InputArgument;
use \Sitewards\StoreStructure\Store\Processor;

class SetupStoreStructureCommand extends Command
{
    /** @var ConfigInterface */
    protected $resourceConfig;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var Processor */
    protected $storeProcessor;

    /***
     * @param ConfigInterface       $resourceConfig
     * @param StoreManagerInterface $storeManager
     * @param Processor             $storeProcessor
     */
    public function __construct(ConfigInterface $resourceConfig, StoreManagerInterface $storeManager, Processor $storeProcessor)
    {
        parent::__construct(null);

        $this->resourceConfig = $resourceConfig;
        $this->storeManager   = $storeManager;
        $this->storeProcessor = $storeProcessor;
    }

    protected function configure()
    {
        $this->setName('sitewards:store-structure:setup');
        $this->setDescription('Creates the website structure defined in the yaml file');
        $this->addArgument('config', InputArgument::REQUIRED, 'The yaml config file path');
        $this->addOption('cleanup', 'c', InputOption::VALUE_NONE, 'Remove all websites, root-categories, stores and store-views which are not in the config');
        $this->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simulate the command execution, do not apply actual change');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->storeProcessor->loadConfiguration(
            $input->getArgument('config'),
            $input->getOption('cleanup'),
            $input->getOption('dry-run')
        );
        $this->storeProcessor->buildStoreStructure();

        return 0;
    }
}