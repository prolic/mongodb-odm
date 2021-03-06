<?php

namespace Doctrine\ODM\MongoDB\Tests\Functional;

require_once __DIR__ . '/../../../../../TestInit.php';

class NestedDocumentsTest extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    public function testSimple()
    {
        $product = new Product();
        $product->title = 'Product';

        $order = new Order();
        $order->title = 'Order';
        $order->product = $product;

        $this->dm->persist($order);
        $this->dm->flush();
        $this->dm->clear();

        $test = $this->dm->getDocumentCollection(__NAMESPACE__.'\Order')->findOne();

        $this->assertInstanceOf('\MongoId', $test['product']['id']);
        $this->assertEquals('Order', $test['title']);
        $this->assertEquals('Product', $test['product']['title']);

        $doc = $this->dm->findOne(__NAMESPACE__.'\Order');
        $this->assertInstanceOf(__NAMESPACE__.'\Order', $order);
        $this->assertTrue(is_string($doc->product->id));
        $this->assertEquals((string) $test['product']['id'], $doc->product->id);
        $this->assertEquals('Order', $doc->title);
        $this->assertEquals('Product', $doc->product->title);

        $this->dm->clear();

        $order = $this->dm->findOne(__NAMESPACE__.'\Order');
        $this->assertInstanceOf(__NAMESPACE__.'\Order', $order);

        $product = $this->dm->findOne(__NAMESPACE__.'\Product');
        $this->assertInstanceOf(__NAMESPACE__.'\Product', $product);

        $order->product->title = 'tesgttttt';
        $this->dm->flush();
        $this->dm->clear();

        $test1 = $this->dm->getDocumentCollection(__NAMESPACE__.'\Product')->findOne();
        $test2 = $this->dm->getDocumentCollection(__NAMESPACE__.'\Order')->findOne();
        $this->assertNotEquals($test1['title'], $test2['product']['title']);

        $order = $this->dm->findOne(__NAMESPACE__.'\Order');
        $product = $this->dm->findOne(__NAMESPACE__.'\Product');
        $this->assertNotEquals($product->title, $order->product->title);
    }

    public function testNestedCategories()
    {
        $category = new Category('Root');
        $child1 = $category->addChild('Child 1');
        $child2 = $child1->addChild('Child 2');
        $this->dm->persist($category);
        $this->dm->flush();
        $this->dm->clear();

        $category = $this->dm->findOne(__NAMESPACE__.'\Category');
        $category->setName('Root Changed');
        $children = $category->getChildren();

        $children[0]->setName('Child 1 Changed');
        $children[0]->getChild('Child 2')->setName('Child 2 Changed');
        $category->addChild('Child 2');
        $this->dm->flush();
        $this->dm->clear();

        $category = $this->dm->findOne(__NAMESPACE__.'\Category');

        $children = $category->getChildren();
        $this->assertEquals('Child 1 Changed', $children[0]->getName());
        $this->assertEquals('Child 2 Changed', $children[0]->getChild(0)->getName());
        $this->assertEquals('Root Changed', $category->getName());
        $this->assertEquals(2, count($category->getChildren()));

        $test = $this->dm->getDocumentCollection(__NAMESPACE__.'\Category')->findOne();
        $this->assertFalse(isset($test['children'][0]['children'][0]['children']));
    }

    public function testNestedReference()
    {
        $test = new Hierarchy('Root');
        $child1 = $test->addChild('Child 1');
        $child2 = $test->addChild('Child 2');
        $this->dm->persist($child1);
        $this->dm->persist($child2);
        $this->dm->persist($test);
        $this->dm->flush();
        $this->dm->clear();

        $test = $this->dm->findOne(__NAMESPACE__.'\Hierarchy', array('name' => 'Root'));
        $this->assertNotNull($test);
        $child1 = $test->getChild('Child 1')->setName('Child 1 Changed');
        $child2 = $test->getChild('Child 2')->setName('Child 2 Changed');
        $test->setName('Root Changed');
        $child3 = $test->addChild('Child 3');
        $this->dm->persist($child3);
        $this->dm->flush();
        $this->dm->clear();

        $test = $this->dm->findOne(__NAMESPACE__.'\Hierarchy');
        $this->assertNotNull($test);
        $this->assertEquals('Root Changed', $test->getName());
        $this->assertEquals('Child 1 Changed', $test->getChild(0)->getName());
        $this->assertEquals('Child 2 Changed', $test->getChild(1)->getName());

        $child3 = $this->dm->findOne(__NAMESPACE__.'\Hierarchy', array('name' => 'Child 3'));
        $this->assertNotNull($child3);
        $child3->setName('Child 3 Changed');
        $this->dm->flush();

        $child3 = $this->dm->findOne(__NAMESPACE__.'\Hierarchy', array('name' => 'Child 3 Changed'));
        $this->assertNotNull($child3);
        $this->assertEquals('Child 3 Changed', $child3->getName());

        $test = $this->dm->getDocumentCollection(__NAMESPACE__.'\Hierarchy')->findOne(array('name' => 'Child 1 Changed'));
        $this->assertFalse(isset($test['children']), 'Test empty array is not stored');
    }
}

/** @Document */
class Hierarchy
{
    /** @Id */
    private $id;

    /** @String */
    private $name;

    /** @ReferenceMany(targetDocument="Hierarchy") */
    private $children = array();

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getChild($name)
    {
        if (is_numeric($name)) {
            return $this->children[$name];
        }
        foreach ($this->children as $child) {
            if ($child->name === $name) {
                return $child;
            }
        }
        return null;
    }

    public function addChild($child)
    {
        if (is_string($child)) {
            $child = new Hierarchy($child);
        }
        $this->children[] = $child;
        return $child;
    }

    public function getChildren()
    {
        return $this->children;
    }
}

/** @MappedSuperclass */
class BaseCategory
{
    /** @String */
    protected $name;

    /** @EmbedMany(targetDocument="ChildCategory") */
    protected $children = array();

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getChild($name)
    {
        if (is_numeric($name)) {
            return $this->children[$name];
        }
        foreach ($this->children as $child) {
            if ($child->name === $name) {
                return $child;
            }
        }
        return null;
    }

    public function addChild($child)
    {
        if (is_string($child)) {
            $child = new ChildCategory($child);
        }
        $this->children[] = $child;
        return $child;
    }

    public function getChildren()
    {
        return $this->children;
    }
}

/** @Document */
class Category extends BaseCategory
{
    /** @Id */
    protected $id;

    public function getId()
    {
        return $this->id;
    }
}

/** @EmbeddedDocument */
class ChildCategory extends BaseCategory
{
}

/** @Document */
class Order
{
    /** @Id */
    public $id;

    /** @String */
    public $title;

    /** @EmbedOne(targetDocument="Product") */
    public $product;
}

/** @Document */
class Product
{
    /** @Id */
    public $id;

    /** @String */
    public $title;
}