<?php
/**
 * Created by PhpStorm.
 * User: toby
 * Date: 25/11/2015
 * Time: 14:49
 */

namespace CubicMushroom\Symfony\BehatFeatureContextBundle\Feature\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use LIMTool\KeyFITBundle\DataFixtures\ORM\AbstractSingleFixture;
use LIMTool\KeyFITBundle\Exception\Feature\Context\FixtureContext\FixtureNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Loads fixtures based on scenario tags
 *
 * @package LIMTool\KeyFITBundle
 */
class FixturesContext implements KernelAwareContext
{
    // -----------------------------------------------------------------------------------------------------------------
    // Properties
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var array
     */
    protected $fixtureNamespaces;

    /**
     * @var Loader
     */
    protected $loader;

    /**
     * @var AbstractFixture[]
     */
    protected $loadedFixtures;

    /**
     * @var ORMExecutor
     */
    protected $executor;


    /**
     * NewFixturesContext constructor.
     *
     * @param array $fixtureNamespaces
     */
    public function __construct(array $fixtureNamespaces)
    {
        foreach ($fixtureNamespaces as $fixtureNamespace) {
            $this->addFixtureNamespace($fixtureNamespace);
        }
    }


    // -----------------------------------------------------------------------------------------------------------------
    // @BeforeScenario
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @BeforeScenario
     *
     * @param BeforeScenarioScope $scope
     */
    public function loadFixturesFromTags(BeforeScenarioScope $scope)
    {
        // We load this here, rather than in the constructor so it's re-initialised on each scenario
        $this->loader = new Loader();

        $tags = $scope->getScenario()->getTags();

        foreach ($tags as $tag) {
            $this->loadFixturesForTag($this->loader, $tag);
        }

        $fixtures = $this->loader->getFixtures();

        if (empty($fixtures)) {
            return;
        }

        $this->clearDb();

        $em = $this->getEntityManager();
        $em->clear();

        $purger         = new ORMPurger();
        $this->executor = new ORMExecutor($em, $purger);
        $this->executor->purge();
        $this->executor->execute($fixtures, true);

        $this->loadedFixtures = $fixtures;
    }


    /**
     * @param string $fixture
     *
     * @return array
     */
    public function getNamespacedFixtures($fixture)
    {
        $fixtures = [];

        foreach ($this->fixtureNamespaces as $fixtureNamespace) {

            $fixtureClass = "{$fixtureNamespace}\\{$fixture}";

            if (class_exists($fixtureClass)) {
                $fixtures[] = $fixtureClass;
            }
        }

        return $fixtures;
    }


    /**
     * Clears the d/b
     *
     * @throws ToolsException
     */
    public function clearDb()
    {
        foreach ($this->getEntityManagers() as $entityManager) {
            $metadata = $this->getMetadata($entityManager);
            if (!empty($metadata)) {
                $tool = new SchemaTool($entityManager);
                $tool->dropSchema($metadata);
                $tool->createSchema($metadata);
            }
        }
    }


    /**
     * Loads the fixtures for a given tag
     *
     * @param Loader $loader
     * @param string $tag
     */
    protected function loadFixturesForTag(Loader $loader, $tag)
    {
        $parts  = explode(':', $tag);
        $prefix = array_shift($parts);

        // Only bother with tags staring 'fix:'
        if ('fix' !== $prefix) {
            return;
        }

        if (empty($parts)) {
            throw new \LogicException('No fixture provided');
        }

        $fixture = array_shift($parts);
        $args    = $parts;

        $fixtureClasses = $this->getNamespacedFixtures($fixture);

        foreach ($fixtureClasses as $fixtureClass) {
            $reflect  = new \ReflectionClass($fixtureClass);
            $instance = $reflect->newInstanceArgs($args);

            if (!$instance instanceof FixtureInterface) {
                throw new \InvalidArgumentException("Class {$fixtureClass} does not implement FixtureInterface");
            }

            $loader->addFixture($instance);

            return;
        }

        throw FixtureNotFoundException::create($fixture);
    }


    /**
     * @AfterScenario
     *
     *
     * @return null
     */
    public function closeDBALConnections()
    {
        /** @var EntityManager $entityManager */
        foreach ($this->getEntityManagers() as $entityManager) {
            $entityManager->clear();
        }
        /** @var Connection $connection */
        foreach ($this->getConnections() as $connection) {
            $connection->close();
        }
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Getters and Setters
    // -----------------------------------------------------------------------------------------------------------------


    /**
     * @param $fixturesDir
     *
     * @return $this
     */
    protected function addFixtureNamespace($fixturesDir)
    {
        if (!isset($this->fixtureNamespaces)) {
            $this->fixtureNamespaces = [];
        }

        if (!in_array($fixturesDir, $this->fixtureNamespaces)) {
            $this->fixtureNamespaces[] = $fixturesDir;
        }

        return $this;
    }


    /**
     * Sets Kernel instance.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }


    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        return $this->kernel->getContainer();
    }


    /**
     * @param EntityManager $entityManager
     *
     * @return array
     */
    protected function getMetadata(EntityManager $entityManager)
    {
        return $entityManager->getMetadataFactory()->getAllMetadata();
    }


    /**
     * @return array
     */
    protected function getEntityManagers()
    {
        return $this->getContainer()->get('doctrine')->getManagers();
    }


    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        $em = $this->kernel->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }


    /**
     * @return Connection[]
     */
    protected function getConnections()
    {
        return $this->kernel->getContainer()->get('doctrine')->getConnections();
    }


    /**
     * @return ORMExecutor
     */
    public function getExecutor()
    {
        return $this->executor;
    }


    /**
     * @return ReferenceRepository
     */
    public function getReferenceRepository()
    {
        return $this->executor->getReferenceRepository();
    }


    /**
     * @param string $fixtureClass
     *
     * @return FixtureInterface
     *
     * @throws \OutOfBoundsException if fixture not found
     */
    public function getFixture($fixtureClass)
    {
        try {
            $userFixture = $this->_getFixture($fixtureClass);
        } catch (\OutOfBoundsException $exception) {
            $fixtures = $this->getNamespacedFixtures($fixtureClass);

            if (empty($fixtures)) {
                throw new \OutOfBoundsException("Fixture {$fixtureClass} not found");
            }

            if (count($fixtures) > 1) {
                throw new \LogicException(
                    "Found multiple {$fixtureClass} fixtures.  Use the full namespace to correct"
                );
            }

            /** @var AbstractSingleFixture $userFixture */
            $userFixture = $this->_getFixture($fixtures[0]);
        }

        return $userFixture;
    }


    /**
     * @param string $fixtureClass
     *
     * @return FixtureInterface
     *
     * @throws \OutOfBoundsException if fixture not found
     */
    protected function _getFixture($fixtureClass)
    {
        foreach ($this->loader->getFixtures() as $fixture) {
            if (is_a($fixture, $fixtureClass)) {
                return $fixture;
            }
        }

        throw new \OutOfBoundsException("Fixture '{$fixtureClass}' not found'");
    }


    /**
     * @param $fixtureClass
     *
     * @return object
     *
     * @throw \OutOfBoundsException if fixture is not found
     */
    public function getFixtureEntity($fixtureClass)
    {
        // Fixture class could be a shorthand, without namespace, so we use getFixture to get the full class name…
        $fixture      = $this->getFixture($fixtureClass);
        $fixtureClass = get_class($fixture);

        $referenceRepository = $this->getReferenceRepository();

        if (!$referenceRepository->hasReference($fixtureClass)) {
            throw new \OutOfBoundsException("Fixture '{$fixtureClass}' not found");
        }

        return $referenceRepository->getReference($fixtureClass);
    }
}