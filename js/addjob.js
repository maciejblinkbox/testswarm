/**
 * JavaScript file for the "addjob" page.
 *
 * @author Timo Tijhof, 2012
 * @since 1.0.0
 * @package TestSwarm
 */
jQuery(function ( $ ) {
	var $runsContainer, $addRunBtn, $runFieldsetClean, cnt;

	$runsContainer = $('#runs-container');
	$runFieldsetClean = $runsContainer.children('fieldset').eq(0).clone().detach();

	$addRunBtn = $('<button>')
		.text('+ Run')
		.addClass('btn')
		.click(function ( e ) {
			e.preventDefault();

			cnt = $runsContainer.children('fieldset').length + 1;

			function fixNum( i, val ) {
				return val.replace( '1', cnt );
			}

			$addRunBtn.before(
				$runFieldsetClean.clone()
					.find('input').val('')
					.end()
					.find('[for*="1"]').attr('for', fixNum)
					.end()
					.find('[id*="1"]').attr('id', fixNum)
					.end()
					.find('legend').text(fixNum)
					.end()
			);
		})
		.appendTo('<div class="form-actions"></div>')
		.parent();

	$runsContainer.append( $addRunBtn );
	
	$cloneRunBtn = $('<button>')
		.text(' Clone last')
		.addClass('btn')
		.click(function ( e ) {
			e.preventDefault();

			var cnt = $runsContainer.children('fieldset').length;
			var newCnt = cnt  1;

			function fixNum( i, val ) {
				return val.replace( cnt, newCnt );
			}

			var field = $runsContainer
				.children('fieldset:last')
				.clone()
				.detach()
				.find('[for*="'  cnt  '"]').attr('for', fixNum)
				.end()
				.find('[id*="'  cnt  '"]').attr('id', fixNum)
				.end()
				.find('legend').text(fixNum)
				.end();

			$addRunBtn.before(field);
		});

		$addRunBtn.append( $cloneRunBtn );
});
