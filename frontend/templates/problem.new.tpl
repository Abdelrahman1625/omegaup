{include file='redirect.tpl'}
{assign var="htmlTitle" value="{#omegaupTitleProblemNew#}"}
{include file='head.tpl'}
{include file='mainmenu.tpl'}
{include file='status.tpl'}

{include file='problem.edit.form.tpl' new='true'}
<script type="text/javascript">
	function generateAlias(title) {
		// Remove accents
		title = title.latinize();

		// Replace whitespace
		title = title.replace(/\s+/g, '-');

		// Remove invalid characters
		title = title.replace(/[^a-zA-Z0-9_-]/g, '');

		return title;
	}

	$(document).ready(function() {
		$('#title').blur(function() {
			$('#alias').val(generateAlias($(this).val())).change();
		});

		$('#alias').change(function() {
			omegaup.getProblem(null, $('#alias').val(), function(problem) {
				if (problem.status !== 'error') {
					// Problem already exists.
					OmegaUp.ui.error('El problema "' + omegaup.escape($('#alias').val()) + '" ya existe. Elige otro nombre');
					$('#alias').focus();
				} else {
					OmegaUp.ui.dismissNotifications();
				}
			});
		});
	});
</script>

{include file='footer.tpl'}
