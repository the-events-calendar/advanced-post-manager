<?php
/**
 * @version 4.4
 */
$action_url = add_query_arg( 'post_type', $GLOBALS['typenow'], admin_url( 'edit.php' ) );
do_action( 'tribe-filters-box' );
?>
<div id="tribe-filters" class="metabox-holder meta-box-sortables">
<div id="filters-wrap" class="postbox">
	<div class="handlediv" title="<?php esc_attr_e( 'Click to toggle', 'advanced-post-manager' ); ?>"></div>
	<h3 title="<?php esc_attr_e( 'Click to toggle', 'advanced-post-manager' ); ?>"><?php esc_html_e( 'Filters & Columns', 'advanced-post-manager' ); ?></h3>
	<form id="the-filters" action="<?php echo esc_url( $action_url ); ?>" method="post">
		<div class="filters">
			<?php $this->filters->output_form(); ?>
		</div>
		<div class="columns">
			<h2><?php esc_html_e( 'Active Columns', 'advanced-post-manager' ); ?></h2>
			<p><?php esc_html_e( 'Drag and drop to order and select which columns are displayed in the entries table.', 'advanced-post-manager' ); ?></p>
			<?php $this->columns->output_form(); ?>
		</div>
		<div class="apm-actions">
			<div class="alignleft actions">
				<input type="submit" name="tribe-apply" value="<?php esc_attr_e( 'Apply', 'advanced-post-manager' ); ?>" class="button-primary" />
				<input type="submit" name="save" value="<?php esc_attr_e( 'Save Filter Set', 'advanced-post-manager' ); ?>" class="button-secondary save" />
				<?php if ( $this->export ) : ?>
					<input type="submit" name="csv" value="Export" title="<?php esc_attr_e( 'Export to CSV', 'advanced-post-manager' ); ?>" class="button-secondary csv" />
				<?php endif; ?>
			</div>
			<div class="alignleft save-options">
				<label for="filter_name"><?php esc_html_e( 'Filter Name', 'advanced-post-manager' ); ?> </label><input type="text" name="filter_name" value="" id="filter_name" />
				<input type="submit" name="tribe-save" value="<?php esc_attr_e( 'Save', 'advanced-post-manager' ); ?>" class="button-primary save" />
				<a href="#" id="cancel-save"><?php esc_html_e( 'Cancel', 'advanced-post-manager' ); ?></a>
			</div>
			<div class="alignright clear-action">
				<input type="submit" name="tribe-clear" value="<?php esc_attr_e( 'Reset to Default', 'advanced-post-manager' ); ?>" class="button-primary button-apm-reset" />
			</div>
		</div>
	</form>
</div>
</div>
