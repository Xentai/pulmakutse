<?php
/**
 * @file
 * Contains \Drupal\Tests\wysiwyg_template\Kernel\Controller\TemplateControllerTest.
 */

namespace Drupal\Tests\wysiwyg_template\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\wysiwyg_template\Controller\TemplateController;
use Drupal\wysiwyg_template\Entity\Template;

/**
 * Tests the template controller object.
 *
 * @coversDefaultClass \Drupal\wysiwyg_template\Controller\TemplateController
 *
 * @group wysiwyg_template
 */
class TemplateControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'node', 'user', 'wysiwyg_template'];

  /**
   * The controller to test.
   *
   * @var \Drupal\wysiwyg_template\Controller\TemplateController
   */
  protected $controller;

  /**
   * An array of available node types.
   *
   * @var \Drupal\node\NodeTypeInterface[]
   */
  protected $nodeTypes;

  /**
   * Templates.
   *
   * @var \Drupal\wysiwyg_template_core\TemplateInterface[]
   */
  protected $templates;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp(); // TODO: Change the autogenerated stub

    $this->controller = TemplateController::create($this->container);

    // Create a few node types.
    foreach (range(1, 3) as $i) {
      $this->nodeTypes[$i] = NodeType::create([
        'type' => strtolower($this->randomMachineName()),
        'name' => $this->randomString(),
      ]);
      $this->nodeTypes[$i]->save();
    }
  }

  /**
   * Tests the js callback.
   */
  public function testJsCallback() {
    // No node types passed, no templates.
    $request = $this->controller->listJson();
    $expected = new \stdClass();
    $expected->imagesPath = FALSE;
    $this->assertEquals($expected, $this->getJson($request->getContent()));

    // Node type, no templates.
    $request = $this->controller->listJson($this->nodeTypes[1]);
    $this->assertEquals($expected, $this->getJson($request->getContent()));

    // Add a few non-node-specific templates.
    foreach (range(0, 4) as $i) {
      $this->templates[$i] = Template::create([
        'id' => strtolower($this->randomMachineName()),
        'label' => $this->randomString(),
        'body' => [
          'value' => $this->randomString(),
        ],
        'node_types' => [],
        'weight' => $i,
      ]);
      $this->templates[$i]->save();
    }

    // No node types passed, 5 templates should be available.
    $request = $this->controller->listJson();
    $json = $this->getJson($request->getContent());
    foreach ($this->templates as $i => $template) {
      $this->assertSame($template->getBody(), $json->templates[$i]->html);
    }

    // Pass in a node type, and any templates not specifying node types should
    // be listed.
    $request = $this->controller->listJson($this->nodeTypes[1]);
    $json = $this->getJson($request->getContent());
    foreach ($this->templates as $i => $template) {
      $this->assertSame($template->getBody(), $json->templates[$i]->html);
    }

    // Add a node type to template 5, and change weight.
    $this->templates[4]->set('node_types', [$this->nodeTypes[2]->id()]);
    $this->templates[4]->set('weight', -42);
    $this->templates[4]->save();

    // Template 5 should not be in this list.
    $request = $this->controller->listJson($this->nodeTypes[1]);
    $json = $this->getJson($request->getContent());
    $this->assertEquals(4, count($json->templates));
    $this->assertSame($this->templates[0]->getBody(), $json->templates[0]->html);
    $this->assertSame($this->templates[1]->getBody(), $json->templates[1]->html);
    $this->assertSame($this->templates[2]->getBody(), $json->templates[2]->html);
    $this->assertSame($this->templates[3]->getBody(), $json->templates[3]->html);

    // Node type 2 should list all templates.
    $request = $this->controller->listJson($this->nodeTypes[2]);
    $json = $this->getJson($request->getContent());
    $this->assertEquals(5, count($json->templates));
    $this->assertSame($this->templates[4]->getBody(), $json->templates[0]->html);
    $this->assertSame($this->templates[0]->getBody(), $json->templates[1]->html);
    $this->assertSame($this->templates[1]->getBody(), $json->templates[2]->html);
    $this->assertSame($this->templates[2]->getBody(), $json->templates[3]->html);
    $this->assertSame($this->templates[3]->getBody(), $json->templates[4]->html);
  }

  /**
   * Helper method to strip template json from js callback return
   *
   * @param $js
   *   The javascript from the callback.
   *
   * @return mixed
   *   The parsed json.
   */
  protected function getJson($js) {
    preg_match('/{.*}/', $js, $matches);
    if (empty($matches[0])) {
      $this->fail('No json found in ' . $js);
    }
    return json_decode($matches[0]);
  }

}
