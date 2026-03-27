<template>
	<div class="sm-overlay" @click.self="onClose">
		<div class="sm-dialog" role="dialog" aria-modal="true" aria-labelledby="sm-title">
			<div class="sm-dialog__header">
				<h2 id="sm-title" class="sm-dialog__title">Merge to PDF</h2>
				<button class="sm-dialog__close" :disabled="merging" @click="onClose">✕</button>
			</div>

			<p class="sm-hint">
				Drag rows to set the page order in the output PDF.
				<span v-if="isPublic"> The PDF will download directly to your device.</span>
			</p>

			<ul class="sm-list" @dragover.prevent>
				<li
					v-for="(node, index) in orderedNodes"
					:key="node.fileid"
					class="sm-item"
					:class="{ 'sm-item--dragging': dragIndex === index }"
					draggable="true"
					@dragstart="onDragStart(index)"
					@dragenter.prevent="onDragEnter(index)"
					@dragend="dragIndex = null">
					<span class="sm-handle" aria-hidden="true">⠿</span>
					<span class="sm-filename">{{ node.basename }}</span>
				</li>
			</ul>

			<p v-if="orderedNodes.length > 30" class="sm-warning">
				Warning: merging more than 30 images may be slow or time out.
			</p>

			<div class="sm-field">
				<label for="sm-output-name" class="sm-label">Output filename</label>
				<input
					id="sm-output-name"
					v-model="outputName"
					type="text"
					class="sm-input"
					placeholder="merged.pdf"
					:disabled="merging" />
			</div>

			<p v-if="errorMsg" class="sm-error" role="alert">{{ errorMsg }}</p>

			<div class="sm-actions">
				<button
					class="button-vue button-vue--vue-primary"
					:disabled="merging || !outputName.trim()"
					@click="doMerge">
					<span v-if="merging" class="sm-spinner" aria-hidden="true"></span>
					{{ merging ? 'Merging…' : 'Merge' }}
				</button>
				<button class="button-vue" :disabled="merging" @click="onClose">
					Cancel
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { getClient, getDefaultPropfind, resultToNode, defaultRootPath } from '@nextcloud/files/dav'

export default {
	name: 'MergeDialog',

	props: {
		nodes:      { type: Array,    required: true },
		shareToken: { type: String,   default: null },
		onClose:    { type: Function, required: true },
	},

	computed: {
		isPublic() {
			return !!this.shareToken
		},
	},

	data() {
		const today = new Date().toISOString().slice(0, 10)
		return {
			orderedNodes: [...this.nodes],
			outputName: `merged-${today}.pdf`,
			merging: false,
			errorMsg: '',
			dragIndex: null,
		}
	},

	methods: {
		onDragStart(index) {
			this.dragIndex = index
		},
		onDragEnter(index) {
			if (this.dragIndex === null || this.dragIndex === index) return
			const list = [...this.orderedNodes]
			const [item] = list.splice(this.dragIndex, 1)
			list.splice(index, 0, item)
			this.orderedNodes = list
			this.dragIndex = index
		},

		async doMerge() {
			this.errorMsg = ''
			this.merging = true
			let name = this.outputName.trim()
			if (!name.toLowerCase().endsWith('.pdf')) name += '.pdf'

			try {
				if (this.isPublic) {
					await this.doPublicMerge(name)
				} else {
					await this.doAuthenticatedMerge(name)
				}
				this.onClose()
			} catch (err) {
				const msg = err.response?.data?.ocs?.data?.error
					?? err.response?.data?.error
					?? err.message
					?? 'An unexpected error occurred.'
				this.errorMsg = msg
				showError(msg)
			} finally {
				this.merging = false
			}
		},

		async doAuthenticatedMerge(name) {
			const url = generateOcsUrl('/apps/stirlingmerge/api/merge')
			const { data } = await axios.post(url, {
				fileIds: this.orderedNodes.map((n) => n.fileid),
				outputName: name,
			})
			const filePath = data?.ocs?.data?.filePath ?? name
			showSuccess(`PDF saved: ${filePath.split('/').pop()}`)

			try {
				const client = getClient()
				const result = await client.stat(
					`${defaultRootPath}${filePath}`,
					{ details: true, data: getDefaultPropfind() },
				)
				if (result?.data) {
					emit('files:node:created', resultToNode(result.data))
				}
			} catch (e) {
				console.warn('[stirlingmerge] Could not refresh files list:', e)
			}
		},

		async doPublicMerge(name) {
			const url = generateUrl('/apps/stirlingmerge/public/merge')
			const resp = await fetch(url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					shareToken: this.shareToken,
					paths: this.orderedNodes.map((n) => n.path),
					outputName: name,
				}),
			})

			if (!resp.ok) {
				let errMsg = `HTTP ${resp.status}`
				try {
					const json = await resp.json()
					errMsg = json.error || errMsg
				} catch (_) { /* ignore */ }
				throw new Error(errMsg)
			}

			const blob = await resp.blob()
			const a = document.createElement('a')
			a.href = URL.createObjectURL(blob)
			a.download = name
			document.body.appendChild(a)
			a.click()
			document.body.removeChild(a)
			URL.revokeObjectURL(a.href)
			showSuccess(`Downloading: ${name}`)
		},
	},
}
</script>

<style scoped>
.sm-overlay {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.5);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10000;
}
.sm-dialog {
	background: var(--color-main-background);
	border-radius: var(--border-radius-large, 8px);
	box-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
	padding: 24px;
	width: 420px;
	max-width: 95vw;
	max-height: 90vh;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.sm-dialog__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.sm-dialog__title {
	font-size: 1.2em;
	font-weight: bold;
	margin: 0;
}
.sm-dialog__close {
	background: none;
	border: none;
	cursor: pointer;
	color: var(--color-text-lighter);
	font-size: 1.2em;
	padding: 4px 8px;
	border-radius: var(--border-radius);
}
.sm-dialog__close:hover { background: var(--color-background-hover); }
.sm-hint { color: var(--color-text-lighter); font-size: 0.9em; margin: 0; }
.sm-list {
	list-style: none;
	padding: 0;
	margin: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}
.sm-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	cursor: grab;
	border-bottom: 1px solid var(--color-border-dark);
	user-select: none;
}
.sm-item:last-child { border-bottom: none; }
.sm-item--dragging { opacity: 0.4; background: var(--color-background-hover); }
.sm-handle { color: var(--color-text-lighter); font-size: 1.2em; cursor: grab; }
.sm-filename { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sm-warning { color: var(--color-warning-text, #e9a80b); font-size: 0.9em; margin: 0; }
.sm-field { display: flex; flex-direction: column; gap: 4px; }
.sm-label { font-size: 0.9em; color: var(--color-text-lighter); }
.sm-input {
	width: 100%;
	padding: 8px 12px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 1em;
	box-sizing: border-box;
}
.sm-input:focus { outline: 2px solid var(--color-primary); border-color: var(--color-primary); }
.sm-error { color: var(--color-error); font-size: 0.9em; margin: 0; }
.sm-actions { display: flex; gap: 8px; justify-content: flex-end; padding-top: 4px; }
.sm-spinner {
	display: inline-block;
	width: 14px;
	height: 14px;
	border: 2px solid currentColor;
	border-top-color: transparent;
	border-radius: 50%;
	animation: sm-spin 0.7s linear infinite;
	vertical-align: middle;
	margin-right: 4px;
}
@keyframes sm-spin { to { transform: rotate(360deg); } }
</style>
