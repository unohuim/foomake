<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";

import BaseDrawer from "../../../components/BaseDrawer.vue";
import ResourceDetailHeaderBreadcrumb from "../../../components/ResourceDetailHeaderBreadcrumb.vue";
import ResourceDetailSection from "../../../components/ResourceDetailSection.vue";
import UiToast from "../../../components/UiToast.vue";
import AuthShell from "../../../layouts/AuthShell.vue";

const props = defineProps({
    shell: { type: Object, required: true },
    payload: { type: Object, required: true },
});

const recipe = ref({ ...(props.payload.recipe || {}) });
const sections = ref({ ...(props.payload.sections || {}) });
const ingredients = ref(normalizeIngredients(props.payload.ingredients || {}));
const csrfToken = computed(() => props.payload.csrf_token || props.payload.csrfToken || "");
const indexUrl = computed(() => props.payload.index_url || "/manufacturing/recipes");
const title = computed(() => recipe.value.name || "Recipe");
const activeVersion = computed(() => recipe.value.active_version || {});
const activeVersionActions = computed(() => activeVersion.value.actions || activeVersion.value.header_menu?.options || []);
const versionLabel = computed(() => activeVersion.value.version_number_display || recipe.value.display_version_number || "-");
const breadcrumbItems = computed(() => [
    { label: "Recipes", url: indexUrl.value, current: false },
    { label: title.value, url: null, current: true },
]);
const recipeTypeOptions = computed(() => Array.isArray(props.payload.recipe_type_options) ? props.payload.recipe_type_options : []);
const showProtectedIngredients = computed(() => !ingredients.value.can_edit && ingredients.value.lines.length === 0);

const headerMenuOpen = ref(false);
const selectedIngredientItemId = ref("");
const ingredientsSaving = ref(false);
const ingredientSavedState = reactive({});
const sectionReloadKey = ref(0);
const editOpen = ref(false);
const editSubmitting = ref(false);
const editErrors = ref(emptyRecipeErrors());
const editGeneralError = ref("");
const editForm = reactive({ name: "", is_active: true, is_default: false });
const deleteOpen = ref(false);
const deleteSubmitting = ref(false);
const deleteError = ref("");
const versionOpen = ref(false);
const versionSubmitting = ref(false);
const versionErrors = ref(emptyVersionErrors());
const versionGeneralError = ref("");
const versionForm = reactive({ recipe_type: "manufacturing", output_quantity: "1.000000" });
const toast = reactive({ visible: false, message: "", type: "success" });
let toastTimeoutId = null;

function emptyRecipeErrors() {
    return { name: [], is_active: [], is_default: [] };
}

function emptyVersionErrors() {
    return { recipe_type: [], output_quantity: [] };
}

function normalizeIngredients(value) {
    return {
        can_edit: Boolean(value.can_edit),
        display_version_id: value.display_version_id || null,
        display_version_number: value.display_version_number || "-",
        item_options: Array.isArray(value.item_options) ? value.item_options : [],
        lines: Array.isArray(value.lines) ? value.lines : [],
        store_url: value.store_url || null,
        update_url_template: value.update_url_template || null,
    };
}

function showToast(type, message) {
    toast.type = type;
    toast.message = message;
    toast.visible = true;

    if (toastTimeoutId) {
        window.clearTimeout(toastTimeoutId);
    }

    toastTimeoutId = window.setTimeout(() => {
        toast.visible = false;
        toastTimeoutId = null;
    }, 1500);
}

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: "same-origin",
        ...options,
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken.value,
            ...(options.headers || {}),
        },
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message || "The request could not be completed.");
        error.payload = data;
        error.status = response.status;
        throw error;
    }

    return data;
}

function hydrateRecipeResponse(data) {
    if (data.recipe && typeof data.recipe === "object") {
        recipe.value = { ...recipe.value, ...data.recipe };
    }

    if (data.ingredients && typeof data.ingredients === "object") {
        ingredients.value = normalizeIngredients({ ...ingredients.value, ...data.ingredients });
    }

    if (data.sections && typeof data.sections === "object") {
        sections.value = { ...sections.value, ...data.sections };
        sectionReloadKey.value += 1;
    }
}

function openEditRecipe() {
    editErrors.value = emptyRecipeErrors();
    editGeneralError.value = "";
    editForm.name = recipe.value.name || "";
    editForm.is_active = recipe.value.version_status !== "ARCHIVED";
    editForm.is_default = Boolean(recipe.value.is_default);
    editOpen.value = true;
}

function openVersion() {
    versionErrors.value = emptyVersionErrors();
    versionGeneralError.value = "";
    versionForm.recipe_type = recipe.value.display_recipe_type || "manufacturing";
    versionForm.output_quantity = recipe.value.display_output_quantity || "1.000000";
    versionOpen.value = true;
}

async function submitEdit() {
    editSubmitting.value = true;
    editErrors.value = emptyRecipeErrors();
    editGeneralError.value = "";

    try {
        const data = await jsonRequest(recipe.value.update_url, {
            method: "PATCH",
            body: JSON.stringify({
                name: editForm.name,
                is_active: editForm.is_active,
                is_default: editForm.is_default,
            }),
        });
        recipe.value = { ...recipe.value, ...(data.data || {}) };
        editOpen.value = false;
        showToast("success", "Recipe updated.");
    } catch (error) {
        editErrors.value = { ...emptyRecipeErrors(), ...(error.payload?.errors || {}) };
        editGeneralError.value = error.payload?.message || "Unable to update recipe.";
    } finally {
        editSubmitting.value = false;
    }
}

async function submitDelete() {
    deleteSubmitting.value = true;
    deleteError.value = "";

    try {
        await jsonRequest(recipe.value.delete_url, { method: "DELETE" });
        window.location.assign(indexUrl.value);
    } catch (error) {
        deleteError.value = error.payload?.message || "Unable to delete recipe.";
        showToast("error", deleteError.value);
    } finally {
        deleteSubmitting.value = false;
    }
}

async function submitVersion() {
    versionSubmitting.value = true;
    versionErrors.value = emptyVersionErrors();
    versionGeneralError.value = "";

    try {
        const data = await jsonRequest(recipe.value.version_store_url, {
            method: "POST",
            body: JSON.stringify({
                recipe_type: versionForm.recipe_type,
                output_quantity: versionForm.output_quantity,
            }),
        });
        hydrateRecipeResponse(data);
        versionOpen.value = false;
        showToast("success", "Recipe version created.");
    } catch (error) {
        versionErrors.value = { ...emptyVersionErrors(), ...(error.payload?.errors || {}) };
        versionGeneralError.value = error.payload?.message || "Unable to create recipe version.";
    } finally {
        versionSubmitting.value = false;
    }
}

function actionEndpoint(action, record) {
    const handlerKey = action.handlerKey || action.type || action.id || "";
    const endpoints = {
        checkoutVersion: record?.checkout_url,
        check_in: record?.check_in_url,
        checkInVersion: record?.check_in_url,
        publish: record?.publish_url,
        publishVersion: record?.publish_url,
        duplicate: record?.duplicate_url,
        duplicateVersion: record?.duplicate_url,
        archive: record?.archive_url,
        archiveVersion: record?.archive_url,
        delete: record?.remove_url || record?.delete_url,
        deleteVersion: record?.remove_url || record?.delete_url,
        make: record?.make_url,
        makeVersion: record?.make_url,
    };

    return action.endpoint
        || endpoints[handlerKey]
        || record?.[`${handlerKey}_url`]
        || "";
}

function actionMethod(action) {
    if (action.method) {
        return action.method;
    }

    if (action.handlerKey === "publishVersion" || action.handlerKey === "archiveVersion" || action.id === "publish" || action.id === "archive") {
        return "PATCH";
    }

    if (action.handlerKey === "deleteVersion" || action.id === "delete" || action.type === "remove") {
        return "DELETE";
    }

    return "POST";
}

async function performVersionAction(action, record = activeVersion.value, refresh = null) {
    if (!action || !record) {
        return;
    }

    if (action.handlerKey === "viewVersion" || action.type === "view") {
        return;
    }

    if (action.handlerKey === "makeVersion" || action.id === "make") {
        await createMakeOrder(record.make_url);
        return;
    }

    const endpoint = actionEndpoint(action, record);

    if (!endpoint) {
        return;
    }

    try {
        const data = await jsonRequest(endpoint, { method: actionMethod(action) });

        if (data?.data?.show_url) {
            window.location.assign(data.data.show_url);
            return;
        }

        hydrateRecipeResponse(data);
        if (typeof refresh === "function") {
            await refresh();
        }
        showToast("success", "Recipe version updated.");
    } catch {
        showToast("error", "Unable to update recipe version.");
    } finally {
        headerMenuOpen.value = false;
    }
}

async function addIngredient() {
    if (!ingredients.value.can_edit || !ingredients.value.store_url || !selectedIngredientItemId.value) {
        return;
    }

    ingredientsSaving.value = true;

    try {
        const data = await jsonRequest(ingredients.value.store_url, {
            method: "POST",
            body: JSON.stringify({ item_id: Number(selectedIngredientItemId.value) }),
        });
        ingredients.value.lines = [...ingredients.value.lines, data.data];
        selectedIngredientItemId.value = "";
        showToast("success", "Ingredient added.");
    } catch {
        showToast("error", "Unable to add ingredient.");
    } finally {
        ingredientsSaving.value = false;
    }
}

async function saveIngredientQuantity(line) {
    if (!ingredients.value.can_edit || !ingredients.value.update_url_template || !line?.id) {
        return;
    }

    ingredientSavedState[line.id] = "saving";

    try {
        const data = await jsonRequest(
            ingredients.value.update_url_template.replace("__LINE__", encodeURIComponent(String(line.id))),
            {
                method: "PATCH",
                body: JSON.stringify({ quantity: line.quantity_input }),
            },
        );
        ingredients.value.lines = ingredients.value.lines.map((entry) => (entry.id === line.id ? data.data : entry));
        ingredientSavedState[line.id] = data.meta?.saved ? "saved" : "";
        window.setTimeout(() => {
            if (ingredientSavedState[line.id] === "saved") {
                ingredientSavedState[line.id] = "";
            }
        }, 1000);
    } catch {
        ingredientSavedState[line.id] = "error";
        showToast("error", "Unable to save ingredient quantity.");
    }
}

async function removeIngredient(line) {
    if (!ingredients.value.can_edit || !line?.remove_url) {
        return;
    }

    try {
        const data = await jsonRequest(line.remove_url, { method: "DELETE" });
        hydrateRecipeResponse(data);
        showToast("success", "Ingredient removed.");
    } catch {
        showToast("error", "Unable to remove ingredient.");
    }
}

async function createMakeOrder(makeUrl) {
    if (!makeUrl) {
        return;
    }

    try {
        const data = await jsonRequest(makeUrl, {
            method: "POST",
            body: JSON.stringify({ runs: "1.000000" }),
        });

        if (data?.data?.show_url) {
            window.location.assign(data.data.show_url);
        }
    } catch {
        showToast("error", "Unable to create make order.");
    }
}

function normalizeVersionRow(record) {
    return {
        ...record,
        display: {
            versionText: record.display?.versionText || record.version_number_display || "-",
            typeText: record.display?.typeText || record.recipe_type || "-",
            contextText: record.display?.contextText || "-",
            statusText: record.display?.statusText || record.status || "-",
            statusTone: record.display?.statusTone || "muted",
            outputQuantityText: record.display?.outputQuantityText || record.output_quantity || "-",
            updatedAtText: record.display?.updatedAtText || record.updated_at || "-",
        },
        formValues: {
            recipe_type: record.formValues?.recipe_type || "manufacturing",
            output_quantity: record.formValues?.output_quantity || "1.000000",
        },
    };
}

function normalizeMakeOrderRow(record) {
    return {
        ...record,
        display: {
            recipeNameText: record.recipe_name || "Unnamed recipe",
            runsText: record.runs_display || "-",
            dueDateText: record.due_date || "No due date",
            totalOutputQuantityText: record.qty_display || "-",
            statusText: record.workflow_state || "-",
            statusTone: record.workflow_state === "DRAFT" ? "muted" : "default",
            versionBadgeText: `v${record.recipe_version_number_display || "-"}`,
            versionBadgeTone: "subtle",
            showUrl: record.show_url || "",
        },
    };
}

function handleSectionCustomAction({ action, record, refresh }) {
    if (action.handlerKey === "openVersionCreate") {
        openVersion();
        return;
    }

    if (action.handlerKey === "createMakeOrder") {
        createMakeOrder(sections.value.makeOrders?.endpoints?.create || "");
        return;
    }

    performVersionAction(action, record, refresh);
}

function handleDocumentClick(event) {
    if (!event.target.closest("[data-recipe-version-menu]")) {
        headerMenuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleDocumentClick);
    if (toastTimeoutId) {
        window.clearTimeout(toastTimeoutId);
    }
});
</script>

<template>
    <Head :title="title" />

    <AuthShell :shell="shell" :title="title" :show-header="false">
        <div class="flex h-full min-h-0 w-full flex-col bg-gray-100">
            <UiToast :visible="toast.visible" :type="toast.type" :message="toast.message" />

            <ResourceDetailHeaderBreadcrumb
                :items="breadcrumbItems"
                :title="title"
                title-class="font-semibold text-xl text-gray-800 leading-tight"
                :home-url="shell.navigation?.dashboardUrl ?? '/dashboard'"
            >
                <template #header>
                    <div class="w-full bg-white px-4 pb-4 pt-4 sm:px-6 sm:pb-6 sm:pt-6 lg:px-8">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Recipe</p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-2">
                                    <h1 class="truncate text-xl font-semibold leading-tight text-gray-800">{{ title }}</h1>
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ recipe.output_item_name || "-" }}</span>
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">{{ activeVersion.display?.outputQuantityText || recipe.display_output_quantity_text || "-" }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-600">
                                    <span>{{ recipe.display_recipe_type_label || recipe.recipe_type_label || "-" }}</span>
                                    <span class="text-gray-400">Version {{ versionLabel }}</span>
                                    <span class="text-gray-400">{{ activeVersion.display?.contextText || "" }}</span>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <button v-if="payload.can_manage" type="button" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900" aria-label="Edit recipe" @click="openEditRecipe">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                    </svg>
                                </button>
                                <button v-if="payload.can_manage" type="button" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-red-200 bg-white text-red-600 transition hover:bg-red-50" aria-label="Delete recipe" @click="deleteOpen = true">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>

                                <div v-if="activeVersionActions.length > 0" class="relative" data-recipe-version-menu>
                                    <button type="button" class="inline-flex cursor-pointer items-stretch rounded-lg border border-slate-300 bg-white shadow-sm transition hover:border-slate-400 hover:bg-slate-50" @click.stop="headerMenuOpen = !headerMenuOpen">
                                        <span class="inline-flex items-center px-3 py-2 text-sm font-semibold uppercase text-slate-700">Version {{ versionLabel }}</span>
                                        <span class="inline-flex items-center border-l border-slate-300 px-2.5 text-slate-500">
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </button>
                                    <div v-if="headerMenuOpen" class="absolute right-0 z-[1300] mt-2 w-72 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg ring-1 ring-black/5" role="menu">
                                        <button v-for="action in activeVersionActions" :key="action.id || action.type || action.label" type="button" class="flex w-full cursor-pointer items-start rounded-lg px-3 py-2 text-left text-sm transition hover:bg-slate-50" @click.stop="performVersionAction(action)">
                                            <span>
                                                <span class="block font-medium text-slate-900">{{ action.label }}</span>
                                                <span class="mt-1 block text-xs leading-5 text-slate-500">{{ action.description }}</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </ResourceDetailHeaderBreadcrumb>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="mx-auto max-w-5xl space-y-0 px-1 pb-8 pt-8 sm:space-y-6 sm:px-6 sm:py-12 lg:px-8">
                    <section v-if="showProtectedIngredients" class="overflow-hidden border border-slate-200 bg-white shadow-sm sm:rounded-2xl">
                        <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3 sm:px-6">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-inset ring-blue-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 8.25A2.25 2.25 0 0 1 6.75 6h10.5A2.25 2.25 0 0 1 19.5 8.25v7.5A2.25 2.25 0 0 1 17.25 18H6.75a2.25 2.25 0 0 1-2.25-2.25v-7.5Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 9.75h9M7.5 12h9M7.5 14.25h5.25" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">Ingredients</p>
                                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Version protected</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-4 py-6 sm:px-6 sm:py-7">
                            <div class="max-w-2xl">
                                <p class="text-sm leading-6 text-slate-600">Recipes are version protected. Check out this draft version to add or change ingredients.</p>

                                <div class="mt-6 flex flex-wrap items-center gap-3">
                                    <button v-if="activeVersion.checkout_url" type="button" class="inline-flex cursor-pointer items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="performVersionAction({ handlerKey: 'checkoutVersion', method: 'POST', endpoint: activeVersion.checkout_url })">Check out version</button>
                                    <span class="text-xs font-medium text-slate-500">Then add ingredients, check in, and publish when ready.</span>
                                </div>

                                <ol class="mt-7 grid gap-3 sm:grid-cols-3">
                                    <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">1</span>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Check out version</p>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">Open the draft for editing.</p>
                                    </li>
                                    <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">2</span>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Add ingredients</p>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">Add items and quantities while the version is checked out.</p>
                                    </li>
                                    <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">3</span>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Check in or publish</p>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">Finish the draft and make it available for use.</p>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </section>

                    <ResourceDetailSection v-else :section="sections.ingredients" :csrf-token="csrfToken">
                        <div class="space-y-4">
                            <div v-if="ingredients.can_edit" class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                <select v-model="selectedIngredientItemId" class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select ingredient</option>
                                    <option v-for="item in ingredients.item_options" :key="item.id" :value="String(item.id)">{{ item.label }} · {{ item.uom_display }}</option>
                                </select>
                                <button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" :disabled="ingredientsSaving || !selectedIngredientItemId" @click="addIngredient">Add</button>
                            </div>
                            <div class="overflow-x-auto overflow-y-visible">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3 text-left font-semibold text-gray-600">Item Name</th>
                                            <th class="px-3 py-3 text-left font-semibold text-gray-600">UOM</th>
                                            <th class="px-3 py-3 text-left font-semibold text-gray-600">Qty</th>
                                            <th class="px-3 py-3 text-right font-semibold text-gray-600">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        <tr v-if="ingredients.lines.length === 0">
                                            <td class="px-3 py-6 text-center text-sm text-gray-500" colspan="4">No ingredients yet.</td>
                                        </tr>
                                        <template v-else>
                                            <tr v-for="line in ingredients.lines" :key="line.id">
                                                <td class="px-3 py-3 text-gray-900">
                                                    <a v-if="line.view_url" :href="line.view_url" class="cursor-pointer hover:text-blue-700">{{ line.item_name }}</a>
                                                    <span v-else>{{ line.item_name }}</span>
                                                </td>
                                                <td class="px-3 py-3 text-gray-600">{{ line.uom }}</td>
                                                <td class="px-3 py-3">
                                                    <div v-if="ingredients.can_edit" class="flex items-center gap-2">
                                                        <input v-model="line.quantity_input" type="text" class="block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @blur="saveIngredientQuantity(line)" @change="saveIngredientQuantity(line)">
                                                        <span v-if="ingredientSavedState[line.id] === 'saved'" class="text-emerald-600">
                                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-8 8.07a1 1 0 0 1-1.42 0l-4-4.035a1 1 0 0 1 1.42-1.41l3.29 3.32 7.29-7.36a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </span>
                                                        <span v-if="ingredientSavedState[line.id] === 'error'" class="text-xs font-medium text-red-600">Error</span>
                                                    </div>
                                                    <span v-else class="text-gray-700">{{ line.quantity_display }}</span>
                                                </td>
                                                <td class="px-3 py-3 text-right">
                                                    <button v-if="ingredients.can_edit && line.remove_url" type="button" class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-gray-300 text-gray-500 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600" aria-label="Remove ingredient" @click="removeIngredient(line)">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </ResourceDetailSection>

                    <ResourceDetailSection v-if="sections.makeOrders" :key="`make-orders-${sectionReloadKey}`" :section="sections.makeOrders" :csrf-token="csrfToken" :normalize-record="normalizeMakeOrderRow" @custom-action="handleSectionCustomAction" />

                    <ResourceDetailSection v-if="sections.versions" :key="`versions-${sectionReloadKey}`" :section="sections.versions" :csrf-token="csrfToken" :normalize-record="normalizeVersionRow" :build-update-payload="(form) => ({ recipe_type: form.recipe_type || 'manufacturing', output_quantity: form.output_quantity || '1.000000' })" @custom-action="handleSectionCustomAction" />
                </div>
            </div>
        </div>

        <BaseDrawer :open="editOpen" labelled-by="recipe-edit-title" panel-class="w-screen max-w-md" @close="editOpen = false">
            <form class="flex h-full flex-col bg-white" @submit.prevent="submitEdit">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="recipe-edit-title" class="text-sm font-semibold">Edit Recipe</h2>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="editGeneralError" class="text-xs text-red-600">{{ editGeneralError }}</p>
                    <label class="block text-xs font-medium text-slate-700">Name<input v-model="editForm.name" class="mt-1 block w-full border-slate-300 text-sm" type="text"></label>
                    <p v-if="editErrors.name?.[0]" class="text-xs text-red-600">{{ editErrors.name[0] }}</p>
                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-700"><input v-model="editForm.is_active" type="checkbox" class="rounded border-slate-300"> Active</label>
                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-700"><input v-model="editForm.is_default" type="checkbox" class="rounded border-slate-300"> Default</label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="editOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer rounded-md bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="editSubmitting">Save</button>
                </div>
            </form>
        </BaseDrawer>

        <BaseDrawer :open="versionOpen" labelled-by="recipe-version-title" panel-class="w-screen max-w-md" @close="versionOpen = false">
            <form class="flex h-full flex-col bg-white" @submit.prevent="submitVersion">
                <div class="bg-[#001f3f] px-5 py-4 text-blue-100">
                    <h2 id="recipe-version-title" class="text-sm font-semibold">Create Recipe Version</h2>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm">
                    <p v-if="versionGeneralError" class="text-xs text-red-600">{{ versionGeneralError }}</p>
                    <label class="block text-xs font-medium text-slate-700">Type<select v-model="versionForm.recipe_type" class="mt-1 block w-full border-slate-300 text-sm"><option v-for="option in recipeTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
                    <p v-if="versionErrors.recipe_type?.[0]" class="text-xs text-red-600">{{ versionErrors.recipe_type[0] }}</p>
                    <label class="block text-xs font-medium text-slate-700">Output Qty<input v-model="versionForm.output_quantity" class="mt-1 block w-full border-slate-300 text-sm" type="text"></label>
                    <p v-if="versionErrors.output_quantity?.[0]" class="text-xs text-red-600">{{ versionErrors.output_quantity[0] }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3">
                    <button type="button" class="cursor-pointer rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="versionOpen = false">Cancel</button>
                    <button type="submit" class="cursor-pointer rounded-md bg-[#001f3f] px-3 py-1.5 text-xs font-semibold text-blue-100 disabled:opacity-60" :disabled="versionSubmitting">Create</button>
                </div>
            </form>
        </BaseDrawer>

        <div v-if="deleteOpen" class="fixed inset-0 z-[1200] bg-black/40" @click.self="deleteOpen = false">
            <div class="mx-auto mt-24 w-full max-w-md border border-slate-200 bg-white p-5 shadow-xl">
                <h2 class="text-base font-semibold text-slate-900">Delete Recipe</h2>
                <p class="mt-2 text-sm text-slate-600">Delete {{ recipe.name }}?</p>
                <p v-if="deleteError" class="mt-3 text-xs text-red-600">{{ deleteError }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="cursor-pointer rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="deleteOpen = false">Cancel</button>
                    <button type="button" class="cursor-pointer rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60" :disabled="deleteSubmitting" @click="submitDelete">Delete</button>
                </div>
            </div>
        </div>
    </AuthShell>
</template>
