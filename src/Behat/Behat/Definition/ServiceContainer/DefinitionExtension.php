<?php

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Behat\Definition\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Behat\Translator\ServiceContainer\TranslatorExtension;
use Behat\Testwork\Cli\ServiceContainer\CliExtension;
use Behat\Testwork\Environment\ServiceContainer\EnvironmentExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ServiceProcessor;
use Behat\Testwork\Suite\ServiceContainer\SuiteExtension;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Behat definition extension.
 *
 * Extends Behat with definition services.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class DefinitionExtension implements Extension
{
    /*
     * Available services
     */
    const FINDER_ID = 'definition.finder';
    const REPOSITORY_ID = 'definition.repository';
    const PATTERN_TRANSFORMER_ID = 'definition.pattern_transformer';
    const WRITER_ID = 'definition.writer';

    /*
     * Available extension points
     */
    const SEARCH_ENGINE_TAG = 'definition.search_engine';

    /**
     * @var ServiceProcessor
     */
    private $processor;

    /**
     * Initializes compiler pass.
     *
     * @param null|ServiceProcessor $processor
     */
    public function __construct(ServiceProcessor $processor = null)
    {
        $this->processor = $processor ? : new ServiceProcessor();
    }

    /**
     * Returns the extension config key.
     *
     * @return string
     */
    public function getConfigKey()
    {
        return 'definition';
    }

    /**
     * Setups configuration for the extension.
     *
     * @param ArrayNodeDefinition $builder
     */
    public function configure(ArrayNodeDefinition $builder)
    {
    }

    /**
     * Loads extension services into temporary container.
     *
     * @param ContainerBuilder $container
     * @param array            $config
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $this->loadFinder($container);
        $this->loadRepository($container);
        $this->loadWriter($container);
        $this->loadPatternTransformer($container);
        $this->loadDefaultSearchEngines($container);
        $this->loadAnnotationReader($container);
        $this->loadDefinitionPrinters($container);
        $this->loadController($container);
    }

    /**
     * Processes shared container after all extensions loaded.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->processSearchEngines($container);
    }

    /**
     * Loads definition finder.
     *
     * @param ContainerBuilder $container
     */
    protected function loadFinder(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Behat\Definition\DefinitionFinder');
        $container->setDefinition(self::FINDER_ID, $definition);
    }

    /**
     * Loads definition repository.
     *
     * @param ContainerBuilder $container
     */
    protected function loadRepository(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Behat\Definition\DefinitionRepository', array(
            new Reference(EnvironmentExtension::MANAGER_ID)
        ));
        $container->setDefinition(self::REPOSITORY_ID, $definition);
    }

    /**
     * Loads definition writer.
     *
     * @param ContainerBuilder $container
     */
    protected function loadWriter(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Behat\Definition\DefinitionWriter', array(
            new Reference(EnvironmentExtension::MANAGER_ID),
            new Reference(self::REPOSITORY_ID)
        ));
        $container->setDefinition(self::WRITER_ID, $definition);
    }

    /**
     * Loads definition pattern transformer.
     *
     * @param ContainerBuilder $container
     */
    protected function loadPatternTransformer(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Behat\Definition\Pattern\TurnipPatternTransformer');
        $container->setDefinition(self::PATTERN_TRANSFORMER_ID, $definition);
    }

    /**
     * Loads default search engines.
     *
     * @param ContainerBuilder $container
     */
    protected function loadDefaultSearchEngines(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Behat\Definition\Search\RepositorySearchEngine', array(
            new Reference(self::REPOSITORY_ID),
            new Reference(self::PATTERN_TRANSFORMER_ID),
            new Reference(TranslatorExtension::TRANSLATOR_ID)
        ));
        $definition->addTag(self::SEARCH_ENGINE_TAG, array('priority' => 50));
        $container->setDefinition(self::SEARCH_ENGINE_TAG . '.repository', $definition);
    }

    /**
     * Loads definition annotation reader.
     *
     * @param ContainerBuilder $container
     */
    protected function loadAnnotationReader(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Behat\Definition\Context\Annotation\DefinitionAnnotationReader');
        $definition->addTag(ContextExtension::ANNOTATION_READER_TAG, array('priority' => 50));
        $container->setDefinition(ContextExtension::ANNOTATION_READER_TAG . '.definition', $definition);
    }

    /**
     * Loads definition printers.
     *
     * @param ContainerBuilder $container
     */
    protected function loadDefinitionPrinters(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Behat\Definition\Printer\ConsoleDefinitionInformationPrinter', array(
            new Reference(CliExtension::OUTPUT_ID),
            new Reference(self::PATTERN_TRANSFORMER_ID),
            new Reference(TranslatorExtension::TRANSLATOR_ID)
        ));
        $container->setDefinition($this->getInformationPrinterId(), $definition);

        $definition = new Definition('Behat\Behat\Definition\Printer\ConsoleDefinitionListPrinter', array(
            new Reference(CliExtension::OUTPUT_ID),
            new Reference(self::PATTERN_TRANSFORMER_ID),
            new Reference(TranslatorExtension::TRANSLATOR_ID)
        ));
        $container->setDefinition($this->getListPrinterId(), $definition);
    }

    /**
     * Loads definition controller.
     *
     * @param ContainerBuilder $container
     */
    protected function loadController(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Behat\Definition\Cli\AvailableDefinitionsController', array(
            new Reference(SuiteExtension::REGISTRY_ID),
            new Reference(self::WRITER_ID),
            new Reference($this->getListPrinterId()),
            new Reference($this->getInformationPrinterId())
        ));
        $definition->addTag(CliExtension::CONTROLLER_TAG, array('priority' => 500));
        $container->setDefinition(CliExtension::CONTROLLER_TAG . '.available_definitions', $definition);
    }

    /**
     * Processes all search engines in the container.
     *
     * @param ContainerBuilder $container
     */
    protected function processSearchEngines(ContainerBuilder $container)
    {
        $references = $this->processor->findAndSortTaggedServices($container, self::SEARCH_ENGINE_TAG);
        $definition = $container->getDefinition(self::FINDER_ID);

        foreach ($references as $reference) {
            $definition->addMethodCall('registerSearchEngine', array($reference));
        }
    }

    /**
     * returns list printer service id.
     *
     * @return string
     */
    protected function getListPrinterId()
    {
        return 'definition.list_printer';
    }

    /**
     * Returns information printer service id.
     *
     * @return string
     */
    protected function getInformationPrinterId()
    {
        return 'definition.information_printer';
    }
}
