<?php
/**
 * XProfile Field Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group xprofile-field
 */
class BP_Test_REST_XProfile_Fields_Endpoint extends WP_Test_REST_Controller_Testcase {
	protected $endpoint;
	protected $bp;
	protected $endpoint_url;
	protected $user;
	protected $server;
	protected $group_id;
	protected $field_id;

	public function set_up() {
		parent::set_up();

		$this->endpoint     = new BP_REST_XProfile_Fields_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->profile->id . '/fields';
		$this->group_id     = $this->bp::factory()->xprofile_group->create();
		$this->field_id     = $this->bp::factory()->xprofile_field->create( [ 'field_group_id' => $this->group_id ] );

		$this->user = static::factory()->user->create( array(
			'role'       => 'administrator',
			'user_email' => 'admin@example.com',
		) );

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$this->bp::set_current_user( $this->user );

		$this->bp::factory()->xprofile_field->create_many( 5, [ 'field_group_id' => $this->group_id ] );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$field = $this->endpoint->get_xprofile_field_object( $data['id'] );
			$this->check_field_data( $field, $data );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_public_get_items() {
		$this->bp::factory()->xprofile_field->create_many( 5, [ 'field_group_id' => $this->group_id ] );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$this->check_field_data(
			$this->endpoint->get_xprofile_field_object( $data[0]['id'] ),
			$data[0]
		);
	}

	/**
	 * @group get_items
	 */
	public function test_public_get_items_with_support_for_the_community_visibility() {
		toggle_component_visibility();

		$this->bp::factory()->xprofile_field->create_many( 5, [ 'field_group_id' => $this->group_id ] );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_include_groups() {
		$g1 = $this->bp::factory()->xprofile_group->create();
		$g2 = $this->bp::factory()->xprofile_group->create();
		$this->bp::factory()->xprofile_field->create_many( 3, [ 'field_group_id' => $g1 ] );
		$this->bp::factory()->xprofile_field->create_many( 2, [ 'field_group_id' => $g2 ] );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'include_groups', array( $g2 ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$this->check_field_data(
			$this->endpoint->get_xprofile_field_object( $data[0]['id'] ),
			$data[0]
		);

		$this->assertEmpty( wp_filter_object_list( $data, array( 'group_id' => $g1 ) ) );
		$this->assertTrue( 2 === count( wp_filter_object_list( $data, array( 'group_id' => $g2 ) ) ) );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$this->bp::set_current_user( $this->user );

		$field = $this->endpoint->get_xprofile_field_object( $this->field_id );
		$this->assertEquals( $this->field_id, $field->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_field_data( $field, $all_data[0] );
	}

	/**
	 * @group get_item
	 */
	public function test_get_public_item() {
		$field = $this->endpoint->get_xprofile_field_object( $this->field_id );
		$this->assertEquals( $this->field_id, $field->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_field_data( $field, $all_data[0] );
	}

	/**
	 * @group get_item
	 */
	public function test_get_public_item_with_support_for_the_community_visibility() {
		toggle_component_visibility();

		$field = $this->endpoint->get_xprofile_field_object( $this->field_id );
		$this->assertEquals( $this->field_id, $field->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_with_invalid_id() {
		$this->bp::set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$this->bp::set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_field_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_field_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_rest_create_item() {
		$this->bp::set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_field_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_without_required_field() {
		$this->bp::set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data( array( 'type' => '' ) );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_invalid_type() {
		$this->bp::set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data( array( 'type' => 'group' ) );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_without_permission() {
		$u = static::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->bp::set_current_user( $u );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$new_name = 'Updated name';
		$this->bp::set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data( [ 'name' => $new_name, 'group_id' => $this->group_id ] );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$object  = end( $all_data );
		$updated = $this->endpoint->get_xprofile_field_object( $object['id'] );

		$this->assertSame( $new_name, $updated->name );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_id() {
		$this->bp::set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$request->add_header( 'content-type', 'application/json' );
		$params  = $this->set_field_data( [ 'group_id' => $this->group_id ] );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data( [ 'group_id' => $this->group_id ] );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_without_permission() {
		$u = static::factory()->user->create();
		$this->bp::set_current_user( $u );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params  = $this->set_field_data( [ 'group_id' => $this->group_id ] );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$this->bp::set_current_user( $this->user );

		$field = $this->endpoint->get_xprofile_field_object( $this->field_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_field_data( $field, $all_data['previous'] );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_id() {
		$this->bp::set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_without_permission() {
		$u = static::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->bp::set_current_user( $u );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group prepare_item
	 */
	public function test_prepare_item() {
		$this->bp::set_current_user( $this->user );

		$field = $this->endpoint->get_xprofile_field_object( $this->field_id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_field_data( $field, $all_data[0] );
	}

	/**
	 * @group additional_fields
	 */
	public function test_additional_fields() {
		$registered_fields = $GLOBALS['wp_rest_additional_fields'];

		bp_rest_register_field( 'xprofile', 'foo_field_key', array(
			'get_callback'    => array( $this, 'get_additional_field' ),
			'update_callback' => array( $this, 'update_additional_field' ),
			'schema'          => array(
				'description' => 'xProfile Field Meta Field',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		), 'field' );

		$this->bp::set_current_user( $this->user );
		$expected = 'bar_field_value';

		// POST
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_field_data( array( 'foo_field_key' => $expected ) );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$create_data = $response->get_data();
		$this->assertTrue( $expected === $create_data[0]['foo_field_key'] );

		// GET
		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $create_data[0]['id'] ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$get_data = $response->get_data();
		$this->assertTrue( $expected === $get_data[0]['foo_field_key'] );

		$GLOBALS['wp_rest_additional_fields'] = $registered_fields;
	}

	/**
	 * @group additional_fields
	 */
	public function test_update_additional_fields() {
		$registered_fields = $GLOBALS['wp_rest_additional_fields'];

		bp_rest_register_field( 'xprofile', 'bar_field_key', array(
			'get_callback'    => array( $this, 'get_additional_field' ),
			'update_callback' => array( $this, 'update_additional_field' ),
			'schema'          => array(
				'description' => 'xProfile Group Meta Field',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		), 'field' );

		$this->bp::set_current_user( $this->user );
		$expected = 'foo_field_value';

		// PUT
		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_field_data( array(
			'name'          => $this->endpoint->get_xprofile_field_object( $this->field_id )->name,
			'group_id'      => $this->group_id,
			'bar_field_key' => $expected,
		) );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$update_data = $response->get_data();
		$this->assertTrue( $expected === $update_data[0]['bar_field_key'] );

		$GLOBALS['wp_rest_additional_fields'] = $registered_fields;
	}

	public function update_additional_field( $value, $data, $attribute ) {
		return bp_xprofile_update_meta( $data->id, 'field', '_' . $attribute, $value );
	}

	public function get_additional_field( $data, $attribute )  {
		return bp_xprofile_get_meta( $data['id'], 'field', '_' . $attribute );
	}

	protected function check_create_field_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$field = $this->endpoint->get_xprofile_field_object( $data[0]['id'] );
		$this->check_field_data( $field, $data[0], 'edit' );
	}

	protected function set_field_data( $args = array() ) {
		return wp_parse_args( $args, array(
			'type'     => 'checkbox',
			'name'     => 'Test Field Name',
			'group_id' => $this->group_id,
		) );
	}

	protected function check_field_data( $field, $data, $context = 'view' ) {
		$this->assertEquals( $field->id, $data['id'] );
		$this->assertEquals( $field->group_id, $data['group_id'] );
		$this->assertEquals( $field->parent_id, $data['parent_id'] );
		$this->assertEquals( $field->type, $data['type'] );
		$this->assertEquals( $field->name, $data['name'] );

		if ( 'view' === $context ) {
			$this->assertEquals( $field->description, $data['description']['rendered'] );
		} else {
			$this->assertEquals( $field->description, $data['description']['raw'] );
		}

		$this->assertEquals( $field->is_required, $data['is_required'] );
		$this->assertEquals( $field->can_delete, $data['can_delete'] );
		$this->assertEquals( $field->field_order, $data['field_order'] );
		$this->assertEquals( $field->option_order, $data['option_order'] );
		$this->assertEquals( strtoupper( $field->order_by ), $data['order_by'] );
		$this->assertEquals( $field->is_default_option, $data['is_default_option'] );

		if ( ! empty( $data['visibility_level'] ) ) {
			$this->assertEquals( $field->visibility_level, $data['visibility_level'] );
		}
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 15, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'group_id', $properties );
		$this->assertArrayHasKey( 'parent_id', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'is_required', $properties );
		$this->assertArrayHasKey( 'can_delete', $properties );
		$this->assertArrayHasKey( 'field_order', $properties );
		$this->assertArrayHasKey( 'option_order', $properties );
		$this->assertArrayHasKey( 'order_by', $properties );
		$this->assertArrayHasKey( 'options', $properties );
		$this->assertArrayHasKey( 'is_default_option', $properties );
		$this->assertArrayHasKey( 'visibility_level', $properties );
		$this->assertArrayHasKey( 'data', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
