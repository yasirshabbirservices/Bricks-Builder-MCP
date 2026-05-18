/**
 * Gutenberg editor: "Edit with Bricks" button
 *
 * https://wordpress.org/gutenberg/handbook/designers-developers/developers/data/data-core-editor/
 */

/**
 * Localized Bricks admin data (renderWithBricks, i18n, etc.)
 *
 * When the post editor canvas runs in the editor-canvas iframe, wp_localize_script on bricks-admin
 * only runs in the parent document — read from parent when same-origin (@since 2.3.3).
 *
 * @return {object|undefined}
 */
function bricksGetGutenbergBricksData() {
	if (window.bricksData) {
		return window.bricksData
	}

	if (window.self !== window.top) {
		try {
			if (window.parent && window.parent.bricksData) {
				return window.parent.bricksData
			}
		} catch (e) {
			// Cross-origin parent (should not happen in wp-admin editor)
		}
	}

	return undefined
}

/**
 * Localized Bricks Gutenberg payload (builderEditLink, ajaxUrl, etc.)
 *
 * wp-blocks may only be localized in the parent when the canvas is iframed (@since 2.3.3).
 *
 * @return {object|undefined}
 */
function bricksGetGutenbergPayload() {
	if (window.bricksGutenbergData) {
		return window.bricksGutenbergData
	}

	if (window.self !== window.top) {
		try {
			if (window.parent && window.parent.bricksGutenbergData) {
				return window.parent.bricksGutenbergData
			}
		} catch (e) {
			// Cross-origin parent
		}
	}

	return undefined
}

function bricksAdminGutenbergEditWithBricks() {
	if (window.self !== window.top) {
		return
	}

	var editWithBricksLink = document.querySelector('#wp-admin-bar-edit_with_bricks a')

	// If the "Edit with Bricks" link is not available in the admin bar, create it (@since 1.8.6)
	if (!editWithBricksLink) {
		editWithBricksLink = document.createElement('a')
		editWithBricksLink.id = 'wp-admin-bar-edit_with_bricks'
		editWithBricksLink.href = window.bricksGutenbergData.builderEditLink
		editWithBricksLink.innerText = window.bricksData.i18n.editWithBricks
	}

	// Add Bricks buttons to Gutenberg: Listen to window.wp.data store changes to remount buttons
	window.wp.data.subscribe(function () {
		setTimeout(function () {
			var postHeaderToolbar = document.querySelector('.edit-post-header-toolbar')

			if (
				postHeaderToolbar &&
				postHeaderToolbar instanceof HTMLElement &&
				!postHeaderToolbar.querySelector('#toolbar-edit_with_bricks')
			) {
				var editWithBricksButton = document.createElement('a')
				editWithBricksButton.id = 'toolbar-edit_with_bricks'
				editWithBricksButton.classList.add('button')
				editWithBricksButton.classList.add('button-primary')
				editWithBricksButton.innerText = editWithBricksLink.innerText
				editWithBricksButton.href = editWithBricksLink.href

				postHeaderToolbar.append(editWithBricksButton)

				// "Edit with Bricks" button click listener
				editWithBricksButton.addEventListener('click', function (e) {
					e.preventDefault()

					var title = window.wp.data.select('core/editor').getEditedPostAttribute('title')
					var postId = window.wp.data.select('core/editor').getCurrentPostId()

					// Add title
					if (!title) {
						window.wp.data.dispatch('core/editor').editPost({ title: 'Bricks #' + postId })
					}

					// Save draft
					window.wp.data.dispatch('core/editor').savePost()

					// Redirect to edit in Bricks builder
					var redirectToBuilder = function (url) {
						setTimeout(function () {
							if (
								window.wp.data.select('core/editor').isSavingPost() ||
								window.wp.data.select('core/editor').isAutosavingPost()
							) {
								redirectToBuilder(url)
							} else {
								window.location.href = url
							}
						}, 400)
					}

					redirectToBuilder(e.target.href)
				})
			}
		}, 1)
	})
}

/**
 * Handles empty block (Gutenberg) editor state for Bricks-enabled posts/pages
 *
 * @since 1.12
 */
function bricksHandleEmptyContent() {
	let rootContainer = document.querySelector('.is-root-container')
	let attempts = 0
	const maxAttempts = 10

	function tryFindContainer() {
		if (attempts >= maxAttempts) {
			return
		}

		rootContainer = document.querySelector('.is-root-container')

		if (!rootContainer) {
			attempts++
			setTimeout(tryFindContainer, 50)
			return
		}

		// Found the container, proceed with normal flow
		if (window.self !== window.top) {
			// Canvas iframe: persist notice through React re-renders when wp.data is available
			if (window.wp && window.wp.data) {
				window.wp.data.subscribe(function () {
					setTimeout(function () {
						handleEmptyContentCore(rootContainer)
					}, 1)
				})
			} else {
				handleEmptyContentCore(rootContainer)
			}
		} else {
			const editorIframe = document.querySelector('iframe[name="editor-canvas"]')
			if (!editorIframe && window.wp && window.wp.data) {
				window.wp.data.subscribe(function () {
					setTimeout(function () {
						handleEmptyContentCore(rootContainer)
					}, 1)
				})
			}
		}
	}

	tryFindContainer()
}

/**
 * Core logic for handling empty content state
 *
 * When Gutenberg is empty, shows a message and two options:
 * 1. "Edit with Bricks" - Redirects to Bricks builder
 * 2. "Use default editor" - Shows default Gutenberg block appender and remove the notice
 *
 * Uses window.wp.data.subscribe to persist through React re-renders
 * Choice of default editor persists until page reload
 *
 * @since 1.12
 */
function handleEmptyContentCore(rootContainer) {
	const bricksData = bricksGetGutenbergBricksData()
	const gutenbergData = bricksGetGutenbergPayload()

	if (
		rootContainer &&
		!rootContainer.querySelector('.bricks-block-editor-notice-wrapper') &&
		bricksData?.renderWithBricks == 1 &&
		(bricksData?.hasBricksData || bricksData?.contentTemplateId) &&
		!window.useDefaultEditor // Only proceed if user hasn't chosen default editor
	) {
		// Hide existing appender block
		rootContainer.querySelectorAll(':scope > *').forEach((el) => {
			if (!el.classList.contains('bricks-block-editor-notice-wrapper')) {
				el.style.display = 'none'
			}
		})

		const editorWrapper = document.createElement('div')
		editorWrapper.className = 'bricks-block-editor-notice-wrapper'

		const message = document.createElement('p')
		message.className = 'bricks-editor-message'

		// Show different message when page is rendered through a Bricks template (@since 2.3.3)
		if (bricksData.contentTemplateId && bricksData.contentTemplateName) {
			message.textContent = bricksData.i18n.bricksTemplateMessage.replace(
				'%s',
				bricksData.contentTemplateName
			)
		} else {
			message.textContent = bricksData.i18n.bricksActiveMessage
		}

		const buttonWrapper = document.createElement('div')
		buttonWrapper.className = 'bricks-editor-buttons'

		const editButton = document.createElement('a')
		editButton.className = 'button button-primary'
		editButton.href = gutenbergData?.builderEditLink || '#'
		editButton.textContent = bricksData.i18n.editWithBricks

		// Handle edit button click: Save post first, then redirect to builder (@since 2.3.3)
		editButton.addEventListener('click', (e) => {
			e.preventDefault()

			if (window.self !== window.top) {
				// We're in an iframe, send message to parent
				window.top.postMessage(
					{
						type: 'bricksOpenBuilder',
						url: gutenbergData?.builderEditLink || ''
					},
					'*'
				)
			} else if (window.wp && window.wp.data) {
				// We're in top window: Save post first, then redirect (fixes href for drafts/auto-drafts)
				const postId = window.wp.data.select('core/editor').getCurrentPostId()
				const title = window.wp.data.select('core/editor').getEditedPostAttribute('title')

				if (!title) {
					window.wp.data.dispatch('core/editor').editPost({ title: 'Bricks #' + postId })
				}

				window.wp.data.dispatch('core/editor').savePost()

				let builderUrl = gutenbergData?.builderEditLink || ''

				// Build URL from permalink after save to ensure it resolves
				const redirectToBuilder = () => {
					setTimeout(() => {
						if (
							window.wp.data.select('core/editor').isSavingPost() ||
							window.wp.data.select('core/editor').isAutosavingPost()
						) {
							redirectToBuilder()
						} else {
							// Get the latest permalink after save
							const permalink = window.wp.data.select('core/editor').getPermalink()

							if (permalink) {
								builderUrl =
									permalink.indexOf('?') !== -1
										? permalink + '&' + bricksData.builderParam + '=run'
										: permalink + '?' + bricksData.builderParam + '=run'
							}

							window.location.href = builderUrl
						}
					}, 400)
				}

				redirectToBuilder()
			} else {
				// Fallback: Navigate directly
				window.location.href = gutenbergData?.builderEditLink || '#'
			}
		})

		const defaultEditorLink = document.createElement('a')
		defaultEditorLink.className = 'button'
		defaultEditorLink.href = '#'
		defaultEditorLink.textContent = bricksData.i18n.useDefaultEditor
		defaultEditorLink.addEventListener('click', (e) => {
			e.preventDefault()
			window.useDefaultEditor = true

			rootContainer.querySelectorAll(':scope > *').forEach((el) => {
				if (!el.classList.contains('bricks-block-editor-notice-wrapper')) {
					el.style.display = ''
				}
			})

			editorWrapper.remove()
		})

		buttonWrapper.append(editButton, defaultEditorLink)
		editorWrapper.append(message, buttonWrapper)
		rootContainer.appendChild(editorWrapper)
	}
}

/*
 * Listen for messages from parent iframe to open Bricks builder
 * Save post before redirecting to ensure the URL resolves (@since 2.3.3)
 *
 * @since 1.12
 */
if (window.self === window.top) {
	window.addEventListener('message', (event) => {
		if (event.data.type === 'bricksOpenBuilder') {
			if (window.wp && window.wp.data) {
				const postId = window.wp.data.select('core/editor').getCurrentPostId()
				const title = window.wp.data.select('core/editor').getEditedPostAttribute('title')

				if (!title) {
					window.wp.data.dispatch('core/editor').editPost({ title: 'Bricks #' + postId })
				}

				window.wp.data.dispatch('core/editor').savePost()

				const redirectToBuilder = () => {
					setTimeout(() => {
						if (
							window.wp.data.select('core/editor').isSavingPost() ||
							window.wp.data.select('core/editor').isAutosavingPost()
						) {
							redirectToBuilder()
						} else {
							const permalink = window.wp.data.select('core/editor').getPermalink()
							let builderUrl = event.data.url

							if (permalink && window.bricksData) {
								builderUrl =
									permalink.indexOf('?') !== -1
										? permalink + '&' + window.bricksData.builderParam + '=run'
										: permalink + '?' + window.bricksData.builderParam + '=run'
							}

							window.location.href = builderUrl
						}
					}, 400)
				}

				redirectToBuilder()
			} else {
				window.location.href = event.data.url
			}
		}
	})
}

document.addEventListener('DOMContentLoaded', function (e) {
	bricksAdminGutenbergEditWithBricks()
	bricksHandleEmptyContent()
})
