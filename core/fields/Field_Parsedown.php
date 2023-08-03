<?php
defined('CMSPATH') or die; // prevent unauthorized access

class Field_Parsedown extends Field {

	public function display() {
		$wrapper_id = uniqid();

		?>
			<style>
				/* wrapper */
				.pd_wrapper {
					box-shadow: inset 0 0.0625em 0.125em rgb(10 10 10 / 5%);
					max-width: 100%;
					width: 100%;
					background-color: #fff;
					border-color: #dbdbdb;
					border-radius: 4px;
					color: #363636;
					-webkit-appearance: none;
					align-items: center;
					border: 1px solid;
					/* display: inline-flex; */
					font-size: 1rem;
					/* height: 2.5em; */
					/* justify-content: flex-start; */
					line-height: 1.5;
					padding-bottom: calc(0.5em - 1px);
					/* padding-left: calc(0.75em - 1px);
					padding-right: calc(0.75em - 1px); */
					padding-top: calc(0.5em - 1px);
					/* position: relative;
					vertical-align: top; */
				}

				/* tab header stuff */
				.pd_tab_header_row {
					display: flex;
					gap: 1rem;
					padding-left: calc(0.75em - 1px);
					padding-right: calc(0.75em - 1px);
					/* padding-bottom: calc(0.5em - 1px); */
					border-bottom: 1px solid;
				}

				.pd_tab_title {
					padding-top: calc(0.5em - 1px);
					padding-left: calc(0.75em - 1px);
					padding-right: calc(0.75em - 1px);
					padding-bottom: calc(0.5em - 1px);
					cursor: pointer;
				}

				.pd_tab_title.active {
					border-left: 1px solid;
					border-right: 1px solid;
					border-top: 1px solid;
					box-shadow: 0 4px 2px -2px #121212;
					border-top-left-radius: 4px;
					border-top-right-radius: 4px;
				}

				.pd_text_controls {
					margin-left: auto;
					display: flex;
					gap: 0.25rem;
				}

				.pd_text_option {
					padding-top: calc(0.5em - 1px);
					padding-left: calc(0.75em - 1px);
					padding-right: calc(0.75em - 1px);
					padding-bottom: calc(0.5em - 1px);
					cursor: pointer;
				}

				/* tab content stuff */
				.pd_tab_content {
					display: none;
					padding-top: calc(0.5em - 1px);
					padding-left: calc(0.75em - 1px);
					padding-right: calc(0.75em - 1px);
				}

				.pd_tab_content.active {
					display: block;
				}

				.pd_tab_content textarea.input {
					min-height: 2.5rem;
					height: 10rem;
					min-width: 100%;
				}

				/* markdown bulma fixing */
				/* todo: finish this */
				.preview_content {
					margin-left: 1rem; /* fixes bulma removing space for lists and stuff */
				}

				.preview_content ul {
					list-style: disc; /* actually want bulma to not clean slate things for once */
				}

				.preview_content p {
					margin: 1em 0;
				}

				.preview_content h1, .preview_content h2, .preview_content h3, .preview_content h4, .preview_content h5, .preview_content h6 {
					font-weight: bold;
					margin: 1em 0;
				}

				.preview_content h1 {
					font-size: 2em;
				}
				
				.preview_content h2 {
					font-size: 1.75em;
				}
				
				.preview_content h3 {
					font-size: 1.5em;
				}
				
				.preview_content h4 {
					font-size: 1.25em;
				}
				
				.preview_content h5 {
					font-size: 1em;
				}
				
				.preview_content h6 {

				}
			</style>
			<section class="pd_wrapper" id="<?php echo $wrapper_id; ?>">
				<div class="pd_tab_header_row">
					<p class="pd_tab_title active" data-tab_content="write_content">Write</p>
					<p class="pd_tab_title" data-tab_content="preview_content">Preview</p>
					<div class="pd_text_controls">
						<i class="pd_text_option fas fa-heading" data-start_prefix="### " title="Heading"></i>
						<i class="pd_text_option fas fa-bold" data-start_prefix="**" data-end_prefix="**" title="Bold"></i>
						<i class="pd_text_option fas fa-italic" data-start_prefix="*" data-end_prefix="*"  title="Italicise"></i>
						<i class="pd_text_option fas fa-quote-right" data-start_prefix="> " title="Quote"></i> <?php //fa-block-quote ?>
						<i class="pd_text_option fas fa-code" data-start_prefix="`" data-end_prefix="`" title="Code"></i>
						<i class="pd_text_option fas fa-link" data-start_prefix="[" data-end_prefix="](url)" title="Link"></i>
						<i class="pd_text_option fas fa-list" data-start_prefix="- " title="Bulleted List"></i>
						<i class="pd_text_option fas fa-list" data-start_prefix="#. " title="Numeric List"></i> <?php //fa-list-ol - find better option ?>
					</div>
				</div>
				<div class="pd_content_header_row">
					<div class="pd_tab_content write_content active">
						<textarea id="upload_space" class="input pd_parsedown_content" <?php echo $this->get_rendered_name(); ?> ><?php echo $this->default; ?></textarea>
					</div>
					<div class="pd_tab_content preview_content">
						<p>some content here</p>
					</div>
				</div>
			</section>
			<script>
				let editor = document.getElementById("<?php echo $wrapper_id; ?>");
				let editor_textarea = editor.querySelector(".pd_parsedown_content");
				editor.addEventListener("click", (e)=>{
					if(e.target.classList.contains("pd_tab_title") && !e.target.classList.contains("active")) {
						editor.querySelector(".pd_tab_header_row").querySelector(".active").classList.remove("active");
						editor.querySelector(".pd_content_header_row").querySelector(".active").classList.remove("active");

						e.target.classList.add("active");
						editor.querySelector("." + e.target.dataset.tab_content).classList.add("active");

						if(e.target.dataset.tab_content == "preview_content") {
							/* todo: add uripath */
							fetch("/api/parsedown?markup=" + encodeURIComponent(editor.querySelector(".pd_parsedown_content").value)).then((response) => response.json()).then((data) => {
								console.log(data);
								editor.querySelector(".preview_content").innerHTML = decodeURIComponent(data.data.html.replace(/\+/g, ' '));
							});
						}
					}
					if(e.target.classList.contains("pd_text_option")) {
						/* TODO: figure out a way to handle numbered lists, require blockquotes on newlines, etc */
						let start_offset=0;

						const [start, end] = [editor_textarea.selectionStart, editor_textarea.selectionEnd];
						if(e.target.dataset.start_prefix) {
							editor_textarea.setRangeText(e.target.dataset.start_prefix, start, start);
							start_offset = e.target.dataset.start_prefix.length;
						}
						if(e.target.dataset.end_prefix) {
							editor_textarea.setRangeText(e.target.dataset.end_prefix, end+start_offset, end+start_offset);
						}
						editor_textarea.focus();
						editor_textarea.setSelectionRange(start+start_offset, end+start_offset);
					}
				});

				//todo: get this working, supposed to insert lists, etc on enter if previous line is using it
				/* editor_textarea.addEventListener("keypress", (e)=>{
					if(e.key==="Enter") {
						const [start, end] = [editor_textarea.selectionStart, editor_textarea.selectionEnd];
						console.log(start, end);

						line = editor_textarea.value.slice(editor_textarea.value.lastIndexOf('\n', editor_textarea.selectionStart - 1) + 1,
							((end = editor_textarea.value.indexOf('\n', editor_textarea.selectionStart)) => end > -1 ? end : undefined)());
						console.log(line);
						
						["* ", "- ", "* [ ]"].forEach((item)=>{
							if (line.startsWith(item)) {
								editor_textarea.setRangeText("\n"+item, start, start);
								//editor_textarea.focus();
								//editor_textarea.setSelectionRange(start+item.length, start+item.length);
							}
						});
						
					}
				}); */
			</script>
			<div id="image_upload_form"></div><div id="regular_upload"></div> <?php //fake for js ?>
			<?php $max_upload_size_bytes = File::get_max_upload_size_bytes(); ?>
			<div id='upload_modal' class="modal">
				<div class="modal-background"></div>
				<div class="modal-card">
					<header class="modal-card-head">
						<p class="modal-card-title">Upload images</p>
						<button class="delete" aria-label="close"></button>
					</header>
					<section class="modal-card-body">
						<p>test</p>
						<form id='image_upload_form' action='<?php echo Config::uripath();?>/admin/images/uploadv2' method="POST" enctype="multipart/form-data">
						</form>
					</section>
					<footer class="modal-card-foot">
						<button onclick='document.getElementById("image_upload_form_submit").click();' class="button is-success">Upload</button>
						<button class="button cancel">Cancel</button>
					</footer>
				</div>
			</div>
			<script>
				window.max_upload_size_bytes = <?php echo $max_upload_size_bytes;?>;
				window.uripath = "<?php echo Config::uripath(); ?>";
			</script>
			<script src="<?php echo Config::uripath(); ?>/admin/controllers/images/views/show/script.js"></script>
		<?php
	}



	public function load_from_config($config) {
		$this->name = $config->name ?? 'error!!!';
		$this->id = $config->id ?? $this->name;
		$this->label = $config->label ?? '';
		$this->required = $config->required ?? false;
		$this->description = $config->description ?? '';
		$this->maxlength = $config->maxlength ?? 99999;
		$this->filter = $config->filter ?? 'RAW';
		$this->minlength = $config->minlength ?? 0;
		$this->missingconfig = $config->missingconfig ?? false;
		$this->type = $config->type ?? 'error!!!';
		$this->default = $config->default ?? '<p></p>';
	}

	public function validate() {
		// not a real field, just displays stuff :)
		return true;
	}
}