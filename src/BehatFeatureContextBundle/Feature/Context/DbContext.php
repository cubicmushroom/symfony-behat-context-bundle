<?php
/**
 * Created by PhpStorm.
 * User: toby
 * Date: 13/11/2015
 * Time: 15:32
 */

namespace CubicMushroom\Symfony\BehatFeatureContextBundle\Feature\Context;

use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Doctrine\ORM\EntityManager;
use LIMTool\KeyFITBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Context for checking entities in the d/b
 */
class DbContext extends \PHPUnit_Framework_TestCase implements KernelAwareContext
{
    // -----------------------------------------------------------------------------------------------------------------
    // Properties
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @var FixturesContext
     */
    private $fixturesContext;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * Stores the last checked entity for checking other properties
     *
     * @var object
     */
    protected $currentEntity;


    // -----------------------------------------------------------------------------------------------------------------
    // @BeforeScenario
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Store links to dependent contexts
     *
     * @param BeforeScenarioScope $scope
     *
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        /** @var InitializedContextEnvironment $environment */
        $environment = $scope->getEnvironment();

        $this->fixturesContext = $environment->getContext(FixturesContext::class);
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Steps
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @Given /^I have a clean database$/
     */
    public function iHaveACleanDatabase()
    {
        $container = $this->kernel->getContainer();
        $appRoot   = $container->getParameter('kernel.root_dir');
        $console   = realpath($appRoot.DIRECTORY_SEPARATOR.'console');
        $env       = $container->getParameter('kernel.environment');

        // Drop & re-create the d/b
        passthru("{$console} doctrine:schema:drop --env={$env} --force");
        passthru("{$console} doctrine:schema:create --env={$env}");
    }


    /**
     * @Given /^I have the following "(?P<entityClass>[^"]+)" entity in the database$/
     *
     * @param string    $entityClass
     * @param TableNode $table
     */
    public function iHaveTheFollowingEntityInTheDatabase($entityClass, TableNode $table)
    {
        $propertyAccessory = $this->getContainer()->get('property_accessor');
        $em                = $this->getEntityManager();

        $entity           = new $entityClass();
        $reflectionEntity = new \ReflectionObject($entity);
        foreach ($table->getRowsHash() as $fieldName => $fieldValue) {
            if ($propertyAccessory->isWritable($entity, $fieldName)) {
                $propertyAccessory->setValue($entity, $fieldName, $fieldValue);
            } else {
                $reflectionProperty = $reflectionEntity->getProperty($fieldName);
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($entity, $fieldValue);
            }
        }

        $em->persist($entity);
        $em->flush($entity);
    }


    /**
     * @Given /^I have the following record in the "(?P<table>[^"]+)" database table$/
     *
     * @param string    $table
     * @param TableNode $data
     */
    public function iHaveTheFollowingRecordInTheDatabaseTable($table, TableNode $data)
    {
        $this->getEntityManager()->getConnection()->insert($table, $data->getRowsHash());
    }


    /**
     * @Given /^I find the following "(?P<fixture>[^"]+)" fixture entity$/
     *
     * @param string $fixture
     */
    public function iShouldSeeTheFollowingFixtureEntityInTheDatabase($fixture)
    {
        $this->currentEntity = $this->fixturesContext->getFixtureEntity($fixture);

        if (empty($this->currentEntity)) {
            throw new \RuntimeException('Unable to find entity in d/b');
        }
    }


    /**
     * @Given /^I should see the following "(?P<entityClass>[^"]+)" entity in the database$/
     *
     * @param string    $entityClass
     * @param TableNode $table
     */
    public function iShouldSeeTheFollowingEntityInTheDatabase($entityClass, TableNode $table)
    {
        $repository = $this->getEntityManager()->getRepository($entityClass);

        $criteria = [];
        foreach ($table->getRowsHash() as $fieldName => $fieldValue) {
            if ('null' === $fieldValue) {
                $fieldValue = null;
            }
            $criteria[$fieldName] = $fieldValue;
        }

        // We need to clear the repository, as the entities have probably been updated on the other (server) side
        $repository->clear();

        $this->currentEntity = $repository->findOneBy($criteria);

        if (empty($this->currentEntity)) {
            throw new \RuntimeException('Unable to find entity in d/b');
        }
    }


    /**
     * @Given /^that entity should have property "(?P<property>[^"]*)" equal to (?P<value>"?[^"]*"?)$/
     *
     * @param string $property entity property to test
     * @param string $value    String or number value.  Strings should be quoted in ""s, otherwise the value will be
     *                         treated as a boolean or number
     */
    public function thatEntityShouldHavePropertyEqualTo($property, $value, $entity = null)
    {
        $strippedValue = trim($value, '"');

        if ($strippedValue !== $value) {
            $value = $strippedValue;
        } else {
            switch ($value) {
                case 'null':
                    $value = null;
                    break;

                case 'true':
                    $value = true;
                    break;

                case 'false':
                    $value = false;
                    break;

                default:
                    if (intval($value) == $value) {
                        $value = intval($value);
                    } else {
                        $value = floatval($value);
                    }
            }
        }

        $propertyValue = $this->getPropertyValue($property, $entity);

        $this->assertEquals($value, $propertyValue);
    }


    /**
     * @Given /^that entity should have "(?P<property>[^"]*)" date property between "(?P<low>[^"]*)" and "(?P<high>[^"]*)"$/
     * @Given /^that entity should have "(?P<property>[^"]*)" datetime property between "(?P<low>[^"]*)" and "(?P<high>[^"]*)"$/
     * @Given /^that entity should have "(?P<property>[^"]*)" datetime property equal to "(?P<low>[^"]*)"$/
     *
     * @param string $property
     * @param string $low
     * @param string $high
     */
    public function thatEntityShouldHaveDatePropertyBetweenAnd($property, $low, $high = null)
    {
        $dateProperty = $this->getPropertyValue($property);

        if (is_null($high)) {
            $high = $low;
        }

        $lowDate  = new \DateTimeImmutable($low);
        $highDate = new \DateTimeImmutable($high);

        if ($dateProperty < $lowDate) {
            throw new \RuntimeException(
                sprintf(
                    'Date/time %s is lower than expected value of %s',
                    $dateProperty->format('Y-m-d H:i:s'),
                    $lowDate->format('Y-m-d H:i:s')
                )
            );
        }

        if ($dateProperty > $highDate) {
            throw new \RuntimeException(
                sprintf(
                    'Date/time %s is higher than expected value of %s',
                    $dateProperty->format('Y-m-d H:i:s'),
                    $highDate->format('Y-m-d H:i:s')
                )
            );
        }
    }


    /**
     * @Given /^that entity should have "(?P<property>[^"]+)" json property containing (?P<json>{[^']+})$/
     *
     * @param string $property
     * @param        $json
     *
     * @internal param TableNode $content
     */
    public function thatEntityShouldHaveJsonPropertyContaining($property, $json)
    {
        $expectedData = json_decode($json, true);
        $jsonProperty = json_decode($this->getPropertyValue($property), true);

        ksort($expectedData);
        ksort($jsonProperty);

        if ($expectedData !== $jsonProperty) {
            throw new \RuntimeException(
                sprintf(
                    'JSON field content is not as expected.  Expected %s but got %s',
                    json_encode($expectedData),
                    json_encode($jsonProperty)
                )
            );
        }
    }


    /**
     * @Given /^that entity should be linked to fixture user "(?P<userFixture>[^"]+)"( by property "(?P<userProperty>[^"]+)")?$/
     *
     * @param string $userFixture
     * @param string $userProperty
     */
    public function thatEntityShouldBeLinkedToFixtureUser($userFixture, $userProperty = 'user')
    {
        /** @var User|null $user */
        $user = $this->getPropertyValue($userProperty);

        if (empty($user)) {
            throw new \RuntimeException('Entity user not set');
        }

        $fixtureUser = $this->fixturesContext->getFixtureEntity($userFixture);

        if (!$fixtureUser instanceof User) {
            throw new \LogicException("Fixture '{$userFixture}' is not a user");
        }

        if ($user->getId() !== $fixtureUser->getId()) {
            throw new \RuntimeException(sprintf('Entity user id is %s, but expected %s', $user->getId(), $userFixture));
        }
    }


    /**
     * @Given /^that entity should be linked to user "(?P<userId>[^"]+)"( by property "(?P<userProperty>[^"]+)")?$/
     *
     * @param string $userId
     * @param string $userProperty
     *
     * @deprecated You should use $this->thatEntityShouldBeLinkedToFixtureUser() instead
     */
    public function thatEntityShouldBeLinkedToUser($userId, $userProperty = 'user')
    {
        /** @var User|null $user */
        $user = $this->getPropertyValue($userProperty);

        if (empty($user)) {
            throw new \RuntimeException('Entity user not set');
        }

        if ($user->getId() != $userId) {
            throw new \RuntimeException(sprintf('Entity user id is %s, but expected %s', $user->getId(), $userId));
        }
    }


    /**
     * @Given /^that entity should not be linked to a user$/
     *
     * @return bool
     */
    public function thatEntityShouldNotBeLinkedToAUser()
    {
        /** @var User|null $user */
        $user = $this->getPropertyValue('user');

        if (!empty($user)) {
            throw new \RuntimeException('Entity user not set');
        }

        return true;
    }


    /**
     * To be used in conjunction with fetching a user entity - See DbContext::iShouldSeeTheFollowingEntityInTheDatabase()
     *
     * @Given /^whose password is "(?P<password>[^"]*)"$/
     *
     * @param string $password
     *
     * @return bool
     */
    public function whosePasswordIs($password)
    {
        $user = $this->getCurrentEntity();

        if (!$user instanceof UserInterface) {
            throw new \RuntimeException(
                sprintf('Fetched entity (%s) is not a user, so can\'t check password.', get_class($user))
            );
        }

        $encoder = $this->getSecurityEncoderForUser($user);

        if (!$encoder->isPasswordValid($user->getPassword(), $password, $user->getSalt())) {
            throw new \RuntimeException('Password is not correct');
        }

        return true;
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Getters and Setters
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        return $this->kernel->getContainer();
    }


    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getContainer()->get('doctrine.orm.default_entity_manager');
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
     * @param string $property
     * @param object $entity (optional)
     *
     * @return mixed
     */
    protected function getPropertyValue($property, $entity = null)
    {
        if (is_null($entity)) {
            $entity = $this->getCurrentEntity();
        }

        $pa = $this->getContainer()->get('property_accessor');

        if (!$pa->isReadable($entity, $property)) {
            throw new \RuntimeException("Property {$property} is not readable");
        }


        return $pa->getValue($entity, $property);
    }


    /**
     * @param UserInterface $user
     *
     * @return PasswordEncoderInterface
     */
    protected function getSecurityEncoderForUser(UserInterface $user)
    {
        return $this->getContainer()->get('security.encoder_factory')->getEncoder($user);
    }


    /**
     * @return object
     */
    protected function getCurrentEntity()
    {
        $em = $this->getEntityManager();

        // Clear the manager before attempting to read, as web requests appear to break UoW identities
        $em->clear();

        $entity = $this->currentEntity;

        if (empty($entity)) {
            throw new \RuntimeException('You must perform a step to retrieve an entity before using this step');
        }

        $entity = $em->merge($entity);
        $em->refresh($entity);

        return $entity;
    }
}