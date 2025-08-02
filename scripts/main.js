"use strict";

class UIManager extends self.DOMElementHandler {
    constructor(iRuntime) {
        super(iRuntime, "ui-manager");
        this._uiElements = new Map();

        // Register message handlers for runtime communication
        this.AddDOMElementMessageHandlers([
            ["create-ui", e => this._OnCreateUI(e)],
            ["update-input", e => this._OnUpdateInput(e)],
            ["destroy-ui", e => this._OnDestroyUI(e)]
        ]);

        // Add tick callback for periodic updates
        this._StartTicking();
    }

    // Create UI elements (button and input field)
    _OnCreateUI(data) {
        const elementId = data.elementId || "ui-" + Date.now();
        const canvasLayer = data.canvasLayer || 0;

        // Create container div for UI elements
        const container = document.createElement("div");
        container.id = elementId;
        container.style.position = "absolute";
        container.style.top = "20px";
        container.style.left = "20px";
        container.style.zIndex = "1000";
        container.style.background = "rgba(0, 0, 0, 0.7)";
        container.style.padding = "10px";
        container.style.borderRadius = "5px";
        container.style.color = "white";
        container.style.fontFamily = "Arial, sans-serif";

        // Create a button
        const button = document.createElement("button");
        button.textContent = data.buttonText || "Trigger Action";
        button.style.padding = "8px 16px";
        button.style.marginRight = "10px";
        button.style.background = "#4CAF50";
        button.style.border = "none";
        button.style.borderRadius = "3px";
        button.style.color = "white";
        button.style.cursor = "pointer";
        button.addEventListener("click", () => {
            this.PostToRuntimeElement("ui-action", elementId, { action: "button-clicked", value: data.buttonText });
        });

        // Create a text input
        const input = document.createElement("input");
        input.type = "text";
        input.placeholder = data.inputPlaceholder || "Enter text...";
        input.style.padding = "8px";
        input.style.borderRadius = "3px";
        input.style.border = "1px solid #ccc";
        input.addEventListener("input", (e) => {
            this.PostToRuntimeElement("ui-input", elementId, { value: e.target.value });
        });

        // Append elements to container
        container.appendChild(button);
        container.appendChild(input);

        // Store and append to canvas layer
        this._uiElements.set(elementId, container);
        const htmlWrap = this.GetRuntimeInterface().GetHTMLWrapElement(canvasLayer);
        htmlWrap.appendChild(container);

        // Notify runtime of UI creation
        this.PostToRuntimeElement("ui-created", elementId, { status: "created" });
    }

    // Handle input updates from runtime
    _OnUpdateInput(data) {
        const elementId = data.elementId;
        const inputValue = data.value;
        const container = this._uiElements.get(elementId);
        if (container) {
            const input = container.querySelector("input");
            if (input) {
                input.value = inputValue;
            }
        }
    }

    // Destroy UI elements
    _OnDestroyUI(data) {
        const elementId = data.elementId;
        const container = this._uiElements.get(elementId);
        if (container) {
            container.remove();
            this._uiElements.delete(elementId);
            this.PostToRuntimeElement("ui-destroyed", elementId, { status: "destroyed" });
        }
    }

    // Override CreateElement to support custom UI creation
    CreateElement(elementId, data) {
        // Not used directly since _OnCreateUI handles creation
        return document.createElement("div");
    }

    // Override UpdateState for potential state updates
    UpdateState(elem, data) {
        // Handle additional state updates if needed
    }

    // Tick to handle periodic updates (e.g., repositioning UI based on canvas)
    Tick() {
        for (const [elementId, container] of this._uiElements) {
            // Example: Adjust position if canvas size changes
            const canvas = this.GetRuntimeInterface().GetMainCanvas();
            container.style.maxWidth = `${canvas.clientWidth - 40}px`;
        }
    }

    // Clean up on release
    Release() {
        for (const container of this._uiElements.values()) {
            container.remove();
        }
        this._uiElements.clear();
        super.Release();
    }
}

// Register the UI manager with the runtime
self.RuntimeInterface.AddDOMHandlerClass(UIManager);

// Initialize on startup
self.runOnStartup(function (runtime) {
    // Automatically create a UI when the runtime starts
    runtime.PostToRuntimeComponent("ui-manager", "create-ui", {
        elementId: "main-ui",
        canvasLayer: 0,
        buttonText: "Start Game",
        inputPlaceholder: "Player Name"
    });
});