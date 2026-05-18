/**
 * Bricks Choices.js Integration
 *
 * Wrapper for Choices.js library to integrate with Bricks filters and forms
 *
 * @since 2.3
 */
class BricksChoices {
	constructor(element, options = {}) {
		this.element = element
		this.isMultiple = element.multiple || false
		this.isFilterElement = element.closest('[data-brx-filter]') !== null
		this.choices = null
		this.multiplePlaceholderElement = null

		// Default Bricks options
		this.defaultOptions = {
			searchEnabled: false,
			searchPlaceholderValue: window.bricksData.i18n?.choicesjs?.search || 'Search',
			itemSelectText: '', // Disable default "Press to select" text
			removeItemButton: true,
			removeItemIconText: () =>
				window.bricksData.i18n?.choicesjs?.removeItemIconText || 'Remove item',
			removeItemLabelText: (value, rawValue) =>
				`${window.bricksData.i18n?.choicesjs?.removeItemIconText || 'Remove item'}: ${value}`,
			shouldSort: false,
			allowHTML: true, // Allow count HTML
			classNames: {
				containerOuter: ['bricks-choices'],
				containerInner: ['bricks-choices__inner'],
				input: ['bricks-choices__input'],
				inputCloned: ['bricks-choices__input--cloned'],
				list: ['bricks-choices__list'],
				listItems: ['bricks-choices__list--multiple'],
				listSingle: ['bricks-choices__list--single'],
				listDropdown: ['bricks-choices__list--dropdown'],
				item: ['bricks-choices__item'],
				itemSelectable: ['bricks-choices__item--selectable'],
				itemDisabled: ['bricks-choices__item--disabled'],
				itemChoice: ['bricks-choices__item--choice'],
				description: ['bricks-choices__description'],
				placeholder: ['bricks-choices__placeholder'],
				group: ['bricks-choices__group'],
				groupHeading: ['bricks-choices__heading'],
				button: ['bricks-choices__button'],
				activeState: ['is-active'],
				focusState: ['is-focused'],
				openState: ['is-open'],
				disabledState: ['is-disabled'],
				highlightedState: ['is-highlighted'],
				selectedState: ['is-selected'],
				flippedState: ['is-flipped'],
				loadingState: ['is-loading'],
				invalidState: ['is-invalid'],
				notice: ['bricks-choices__notice'],
				addChoice: ['bricks-choices__item--selectable', 'add-choice'],
				noResults: ['has-no-results'],
				noChoices: ['has-no-choices']
			}
		}

		// Merge user options with defaults
		this.options = { ...this.defaultOptions, ...options }

		this.init()
	}

	init() {
		// Wait for DOM to be ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => this.initChoices())
		} else {
			this.initChoices()
		}
	}

	initChoices() {
		// Destroy existing instance if exists
		if (this.choices) {
			this.choices.destroy()
		}

		// Initialize Choices.js
		this.choices = new BrxChoices(this.element, this.options)
		this.setupMultiplePlaceholder()

		// Bind events
		this.bindEvents()

		// Store instance on element for later access
		this.element._bricksChoices = this
	}

	bindEvents() {
		if (!this.choices) return

		// Listen to Choices events
		this.element.addEventListener('change', (event) => {
			this.toggleMultiplePlaceholder()

			// Trigger native change event for Bricks filters
			if (this.isFilterElement) {
				this.handleFilterChange(event)
			}
		})

		this.element.addEventListener('choice', (event) => {
			// Custom event when choice is selected
			this.handleChoice(event)
		})
	}

	handleFilterChange(event) {
		// Let Bricks filter system handle the change
		// The native change event will be picked up by bricksSelectFilterFn
	}

	handleChoice(event) {
		// Additional custom handling if needed
	}

	/**
	 * Update choices dynamically (for AJAX filter updates)
	 */
	updateChoices(newChoices) {
		if (!this.choices) return

		this.choices.clearStore()
		this.choices.setChoices(newChoices, 'value', 'label', true)
		this.toggleMultiplePlaceholder()
	}

	/**
	 * Get current value(s)
	 */
	getValue() {
		if (!this.choices) return null
		return this.choices.getValue()
	}

	/**
	 * Set value(s)
	 */
	setValue(value) {
		if (!this.choices) return
		this.choices.setChoiceByValue(value)
	}

	/**
	 * Destroy instance
	 */
	destroy() {
		if (this.multiplePlaceholderElement) {
			this.multiplePlaceholderElement.remove()
			this.multiplePlaceholderElement = null
		}

		if (this.choices) {
			this.choices.destroy()
			this.choices = null
		}
	}

	/**
	 * Enable
	 */
	enable() {
		if (this.choices) {
			this.choices.enable()
		}
	}

	/**
	 * Disable
	 */
	disable() {
		if (this.choices) {
			this.choices.disable()
		}
	}

	setupMultiplePlaceholder() {
		const placeholderText = this.options.placeholderValue || this.element.dataset?.placeholder || ''

		if (!this.choices || !this.isMultiple || this.options.searchEnabled || !placeholderText) {
			return
		}

		if (this.multiplePlaceholderElement) {
			this.multiplePlaceholderElement.remove()
		}

		const containerInner = this.choices.containerInner?.element

		if (!containerInner) {
			return
		}

		const placeholder = document.createElement('div')
		placeholder.className = 'bricks-choices__placeholder bricks-choices__placeholder--multiple'
		placeholder.textContent = placeholderText
		placeholder.tabIndex = 0

		const openDropdown = () => {
			if (!this.choices || this.choices.containerOuter?.isDisabled) {
				return
			}

			this.choices.showDropdown()
		}

		placeholder.addEventListener('click', openDropdown)
		placeholder.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault()
				openDropdown()
			}
		})

		containerInner.appendChild(placeholder)

		this.multiplePlaceholderElement = placeholder
		this.toggleMultiplePlaceholder()
	}

	toggleMultiplePlaceholder() {
		if (!this.multiplePlaceholderElement) {
			return
		}

		const hasValue = Array.from(this.element.selectedOptions || []).some(
			(option) => option.value !== ''
		)

		this.multiplePlaceholderElement.style.display = hasValue ? 'none' : ''
	}
}

/**
 * Auto-initialize Choices.js on elements with data attribute
 * Currently used in the builder for filter-select elements
 */
const bricksChoicesFn = new BricksFunction({
	parentNode: document,
	selector: 'select[data-brx-choices="true"]:not([data-brx-filter])',
	forceReinit: (element, index) => {
		return !bricksIsFrontend
	},
	windowVariableCheck: ['BrxChoices'],
	eachElement: (element) => {
		const instanceId = element.dataset?.scriptId || false

		if (!instanceId) {
			return
		}

		// Destroy existing splideJS instance
		if (window.bricksData.choicesInstances.hasOwnProperty(instanceId)) {
			window.bricksData.choicesInstances[instanceId].destroy()
		}
		// Get custom options from data attribute
		const customOptions = element.dataset.brxChoicesOptions
			? JSON.parse(element.dataset.brxChoicesOptions)
			: {}

		// Initialize
		const choicesInstance = new BricksChoices(element, customOptions)

		// Save instance reference in window.bricksData
		window.bricksData.choicesInstances[instanceId] = choicesInstance
	}
})

function bricksChoices() {
	bricksChoicesFn.run()
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', bricksChoices)
