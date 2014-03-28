			<div id="header" class="navbar navbar-static-top" role="navigation">
				<div class="navbar-inner">
					<div class="container">
						<div class="navbar-header">
							<a class="navbar-brand" href="/index.php">
								<img src="/media/omegaup_curves.png" alt="OmegaUp" />
							</a>
						</div>
						<ul class="nav navbar-nav">
							<li id="nav-arena"{if isset($currentSection) && $currentSection == 'arena'} class="active"{/if}><a href='/arena'>{#navArena#}</a></li>
							{if $LOGGED_IN eq '1'}
								<li id="nav-contests"><a href='/contests.php'>{#navMyContests#}</a></li>
								<li id="nav-problems">
									<a href='#' class="dropdown-toggle" data-toggle="dropdown"><span>{#wordsProblems#}</span><span class="caret"></span></a>
									<ul class="dropdown-menu">
										<li><a href="/myproblems.php">{#navMyProblems#}</a></li>
										<li><a href="/probs.php">{#wordsProblems#}</a></li>
									</ul>
								</li>
							{else}
								<li id="nav-problems"><a href='/probs.php'>{#wordsProblems#}</a></li>
							{/if}
							<li id="nav-rank"><a href='/rank.php'>{#navRanking#}</a></li>
							<li><a href='http://blog.omegaup.com/'>{#navBlog#}</a></li>
							<li><a href='https://omegaup.com/preguntas/'>{#navQuestions#}</a></li>
						</ul>
						
						<ul class="nav navbar-nav navbar-right">
							{if $LOGGED_IN eq '1'}
								<li class="dropdown">
								<a href="#" class="dropdown-toggle" id="user-dropdown" data-toggle="dropdown"><span>{$CURRENT_USER_GRAVATAR_URL_32}&nbsp;&nbsp; {$CURRENT_USER_USERNAME}<span class="caret"></span></a>
									<ul class="dropdown-menu">
									 <li><a href='/profile.php'>{#navViewProfile#}</a></li>
									 <li><a href='/logout.php'>{#navLogOut#}</a></li>
									</ul>
								</li>	
							{else}
								<li><a href='/login.php?redirect={$smarty.server.REQUEST_URI|escape:'url'}'>{#navLogIn#}</a></li>
							{/if}
							
							{if $CURRENT_USER_IS_ADMIN eq '1'}
								<li id="grader-status" class="dropdown">
									<a href="#" class="dropdown-toggle" data-toggle="dropdown"><span id="grader-count"><img src="/media/waitcircle.gif" /></span> <span class="caret"></span></a>
									<ul class="dropdown-menu">
									</ul>
								</li>
							{/if}
						</ul>
						{if $CURRENT_USER_IS_ADMIN eq '1'}
						<script>
							function updateGraderStatus() {
								$("#grader-status > a").removeClass("grader-error grader-ok grader-warning grader-unknown");
								$("#grader-count").html("<img src='/media/waitcircle.gif' />");
								var html = "<li><a href='/admin/'>Admin</a></li>";
								omegaup.getGraderStats(function(stats){	
									if (stats && stats.status == "ok") {
										var graderInfo = stats.grader;
										var queueLength = -1;

										if (graderInfo.status == "ok") {
											var now = new Date().getTime() / 1000;
											if (graderInfo.queues) {
												queueLength = graderInfo.queues['#default'].run_queue_length +
													graderInfo.queues['#default'].running.length;
											}
											if (queueLength < 5) {
												$("#grader-status > a").addClass("grader-ok");
											} else {
												$("#grader-status > a").addClass("grader-warning");
											}
											html += "<li><a href=\"#\">Grader OK</a></li>";
											html += "<li><a href=\"#\">Embedded runner: " + graderInfo.embedded_runner + "</a></li>";
											html += "<li><a href=\"#\">Queues: <pre style=\"width: 50em;\">" + OmegaUp.ui.prettyPrintJSON(graderInfo.queues) + "</pre></a></li>";
										} else {
											$("#grader-status > a").addClass("grader-error");
											html += "<li><a href=\"#\">Grader DOWN</a></li>";
										}

										$("#grader-count").html(queueLength);
									} else {
										$("#grader-status > a").addClass("grader-unknown");
										html += "<li><a href=\"#\">Grader DOWN</a></li>";
										html += "<li><a href=\"#\">API api/grader/status call failed:";
										html += stats.error.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
										html += "</a></li>";
										$("#grader-count").html('?');
									}
									$("#grader-status .dropdown-menu").html(html);
								});
							}

							updateGraderStatus();
							setInterval(updateGraderStatus,	30000);
						</script>
						{/if}
					</div>
				</div>
			</div>
