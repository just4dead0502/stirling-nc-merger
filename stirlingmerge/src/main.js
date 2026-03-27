import { registerFileAction, FileAction } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import { createApp } from 'vue'
import MergeDialog from './components/MergeDialog.vue'

const ALLOWED_MIMES = new Set([
	'image/jpeg',
	'image/png',
	'image/webp',
	'image/gif',
	'image/tiff',
])

// Two paths converging into one — "merge" metaphor
const MERGE_ICON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
  <path d="M17 20.41L18.41 19 15 15.59 13.59 17 17 20.41M7.5 8H11v5.59L5.59 19 7 20.41l6-6V8h3.5L12 3.5 7.5 8Z"/>
</svg>`

/**
 * Returns the public share token if we are on a public share page, null otherwise.
 */
function getPublicShareToken() {
	// NC sets a hidden input with the share token on public pages
	const el = document.getElementById('sharingToken')
	if (el?.value) return el.value
	// Fallback: parse /s/{token} from the URL
	const match = window.location.pathname.match(/\/s\/([^/?#]+)/)
	return match?.[1] ?? null
}

function showMergeDialog(nodes, shareToken) {
	const container = document.createElement('div')
	document.body.appendChild(container)

	const app = createApp(MergeDialog, {
		nodes,
		shareToken,
		onClose() {
			app.unmount()
			container.remove()
		},
	})
	app.mount(container)
}

registerFileAction(new FileAction({
	id: 'stirlingmerge',
	displayName: () => t('stirlingmerge', 'Merge to PDF'),
	iconSvgInline: () => MERGE_ICON,
	enabled(nodes) {
		if (nodes.length < 2) return false
		return nodes.every((n) => ALLOWED_MIMES.has(n.mime))
	},
	// exec is required by FileAction validation (single-file fallback, no-op here)
	exec(node, view, dir) {
		return Promise.resolve(null)
	},
	execBatch(nodes, view, dir) {
		const shareToken = getPublicShareToken()
		showMergeDialog(nodes, shareToken)
		return Promise.resolve(nodes.map(() => null))
	},
}))
