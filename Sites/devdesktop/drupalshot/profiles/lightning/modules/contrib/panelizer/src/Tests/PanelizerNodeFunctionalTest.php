<?php

/**
 * @file
 * Contains \Drupal\panelizer\Tests\PanelizerNodeFunctionalTest.
 */

namespace Drupal\panelizer\Tests;

use Drupal\block_content\Entity\BlockContent;
use Drupal\simpletest\WebTestBase;

/**
 * Basic functional tests of using Panelizer with nodes.
 *
 * @group panelizer
 */
class PanelizerNodeFunctionalTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'ctools',
    'ctools_block',
    'layout_plugin',
    'node',
    'panelizer',
    'panelizer_test',
    'panels',
    'panels_ipe',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $user = $this->drupalCreateUser([
      'administer node display',
      'administer nodes',
      'administer pages',
      'administer content types',
      'create page content',
      'create article content',
      'administer panelizer',
      'access panels in-place editing',
      'view the administration theme',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests the admin interface to set a default layout for a bundle.
   */
  public function testWizardUI() {
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->clickLink('Panelize this view mode');
    // General settings step.
    $edit = [
      'enable' => TRUE,
      'custom' => TRUE,
    ];

    // Contexts step.
    $this->drupalPostForm(NULL, $edit, 'Next');
    $this->assertText('panelizer_context_entity', 'The current entity context is present.');

    // Layout selection step.
    $this->drupalPostForm(NULL, [], 'Next');

    // Content step. Add the Node block to the top region.
    $this->drupalPostForm(NULL, [], 'Next');
    $this->clickLink('Add new block');
    $this->clickLink('Title');
    $edit = [
      'region' => 'middle',
    ];
    $this->drupalPostForm(NULL, $edit, 'Add block');

    // Finish the wizard.
    $this->drupalPostForm(NULL, [], 'Finish');

    // Check that the general setting was saved.
    $this->assertFieldChecked('edit-custom');

    // Now change the setting and then cancel changes.
    $this->drupalPostForm(NULL, ['custom' => FALSE], 'Update');
    $this->assertNoFieldChecked('edit-custom');
    $this->drupalPostForm(NULL, [], 'Cancel');
    $this->assertFieldChecked('edit-custom');

    // Now change and save the general setting.
    $this->drupalPostForm(NULL, ['custom' => FALSE], 'Update and save');
    $this->assertNoFieldChecked('edit-custom');
    $this->drupalPostForm(NULL, [], 'Cancel');
    $this->assertNoFieldChecked('edit-custom');

    // Add another block at the Content step and then save changes.
    $this->clickLink('Content');
    $this->clickLink('Add new block');
    $this->clickLink('Body');
    $edit = [
      'region' => 'middle',
    ];
    $this->drupalPostForm(NULL, $edit, 'Add block');
    $this->assertText('entity_field:node:body', 'The body block was added successfully.');
    $this->drupalPostForm(NULL, [], 'Save');
    $this->clickLink('Content');
    $this->assertText('entity_field:node:body', 'The body block was saved successfully.');

    // Check that the Manage Display tab changed now that Panelizer is set up.
    // Also, the field display table should be hidden.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertLink('This display mode is managed by Panelizer. Click here to go to its settings.');
    $this->assertNoRaw('<div id="field-display-overview-wrapper">');

    // Disable Panelizer for the default display mode.
    // This should bring back the field overview table at Manage Display
    // and make the "Panelize this view mode" link to point to the Edit
    // Wizard UI.
    $this->clickLink('This display mode is managed by Panelizer. Click here to go to its settings.');
    $edit = [
      'enable' => FALSE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertLink('Panelize this view mode');
    $this->assertLinkByHref('admin/structure/panelizer/edit/node__article__default');
    $this->assertRaw('<div id="field-display-overview-wrapper">');
  }

  /**
   * Tests rendering a node with Panelizer default.
   */
  public function testPanelizerDefault() {
    $this->drupalPostForm('admin/structure/types/manage/page/display', [
      'panelizer[enable]' => TRUE,
      'panelizer[custom]' => TRUE,
    ], 'Save');
    /** @var \Drupal\panelizer\PanelizerInterface $panelizer */
    $panelizer = \Drupal::service('panelizer');
    $displays = $panelizer->getDefaultPanelsDisplays('node', 'page', 'default');
    $display = $displays['default'];
    $display->addBlock([
      'id' => 'panelizer_test',
      'label' => 'Panelizer test',
      'provider' => 'block_content',
      'region' => 'middle',
    ]);
    $panelizer->setDefaultPanelsDisplay('default', 'node', 'page', 'default', $display);

    // Create a node, and check that the IPE is visible on it.
    $node = $this->drupalCreateNode(['type' => 'page']);
    $out = $this->drupalGet('node/' . $node->id());
    $this->verbose($out);
    $elements = $this->xpath('//*[@id="panels-ipe-content"]');
    if (is_array($elements)) {
      $this->assertIdentical(count($elements), 1);
    }
    else {
      $this->fail('Could not parse page content.');
    }

    // Check that the block we added is visible.
    $this->assertText('Panelizer test');
    $this->assertText('Abracadabra');
  }

}
