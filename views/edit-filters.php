<?php
$action_url = add_query_arg( 'post_type', $GLOBALS['typenow'], admin_url( 'edit.php' ) );
do_action( 'tribe-filters-box' );
 ?>
<div id="tribe-filters" class="metabox-holder meta-box-sortables">
<div id="filters-wrap" class="postbox">
	<div class="handlediv" title="<?php esc_attr_e( 'Click to toggle', 'tribe-apm' ); ?>"></div>
	<h3 title="<?php esc_attr_e( 'Click to toggle', 'tribe-apm' ); ?>"><?php esc_html_e( 'Filters & Columns', 'tribe-apm' ); ?></h3>
	<form id="the-filters" action="<?php echo esc_url( $action_url ); ?>" method="post">
		<div class="filters">
			<?php $this->filters->output_form(); ?>
		</div>
		<div class="columns">
			<h2><?php esc_html_e( 'Active Columns', 'tribe-apm' ); ?></h2>
			<p><?php esc_html_e( 'Drag and drop to order and select which columns are displayed in the entries table.', 'tribe-apm' ); ?></p>
			<?php $this->columns->output_form(); ?>
		</div>
		<div class="apm-actions">
			<div class="alignleft actions">
				<input type="submit" name="tribe-apply" value="<?php esc_attr_e( 'Apply', 'tribe-apm' ); ?>" class="button-primary" />
				<input type="submit" name="save" value="<?php esc_attr_e( 'Save Filter Set', 'tribe-apm' ); ?>" class="button-secondary save" />
				<?php if ( $this->export ) : ?>
					<input type="submit" name="csv" value="Export" title="<?php esc_attr_e( 'Export to CSV', 'tribe-apm' ); ?>" class="button-secondary csv" />
				<?php endif; ?>
			</div>
			<div class="alignleft save-options">
				<label for="filter_name"><?php esc_html_e( 'Filter Name', 'tribe-apm' ); ?> </label><input type="text" name="filter_name" value="" id="filter_name" />
				<input type="submit" name="tribe-save" value="<?php esc_attr_e( 'Save', 'tribe-apm' ); ?>" class="button-primary save" />
				<a href="#" id="cancel-save"><?php esc_html_e( 'Cancel', 'tribe-apm' ); ?></a>
			</div>
			<div class="alignright clear-action">
				<input type="submit" name="tribe-clear" value="<?php esc_attr_e( 'Reset to Default', 'tribe-apm' ); ?>" class="button-primary button-apm-reset" />
			</div>
		</div>
	</form>
</div>
</div>
