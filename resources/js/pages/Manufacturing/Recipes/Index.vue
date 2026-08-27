<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";

import ResourceCardGrid from "../../../components/ResourceCardGrid.vue";
import ResourceCreateDrawer from "../../../components/ResourceCreateDrawer.vue";
import ResourceIndex from "../../../components/ResourceIndex.vue";
import UiToast from "../../../components/UiToast.vue";
import AuthShell from "../../../layouts/AuthShell.vue";

const props = defineProps({
    shell: {
        type: Object,
        required: true,
    },
    crudConfig: {
        type: Object,
        required: true,
    },
    payload: {
        type: Object,
        required: true,
    },
});

const recipes = ref(Array.isArray(props.payload.initial_rows) ? [...props.payload.initial_rows] : []);
const search = ref("");
const sort = ref({
    column: "updated_at",
    direction: "desc",
});
const loading = ref(false);
const listError = ref("");
const toast = ref(null);
const toastTimer = ref(null);

const createDrawerOpen = ref(false);
const createSubmitting = ref(false);
const createError = ref("");
const createErrors = ref(emptyCreateErrors());
const createOnlyWithoutRecipe = ref(false);
const createManufacturingOutputQuantity = ref("");
const createForm = reactive(emptyCreateForm());

const editDrawerOpen = ref(false);
const editSubmitting = ref(false);
const editRecipeId = ref(null);
const editError = ref("");
const editErrors = ref(emptyEditErrors());
const editForm = reactive(emptyEditForm());

const versionDrawerOpen = ref(false);
const versionSubmitting = ref(false);
const versionRecipeId = ref(null);
const versionRecipeName = ref("");
const versionError = ref("");
const versionErrors = ref(emptyVersionErrors());
const versionForm = reactive(emptyVersionForm());

const archiveDrawerOpen = ref(false);
const archiveSubmitting = ref(false);
const archiveRecipeId = ref(null);
const archiveRecipeName = ref("");
const archiveError = ref("");

const labels = computed(() => props.crudConfig.labels ?? {});
const permissions = computed(() => props.crudConfig.permissions ?? {});
const manufacturableItems = computed(() => Array.isArray(props.payload.manufacturable_items) ? props.payload.manufacturable_items : []);
const csrfToken = computed(() => props.payload.csrfToken ?? props.payload.csrf_token ?? "");
const actions = computed(() => props.crudConfig.actions ?? []);

function emptyCreateErrors() {
    return {
        item_id: [],
        recipe_type: [],
        name: [],
        output_quantity: [],
        is_active: [],
        is_default: [],
    };
}

function emptyEditErrors() {
    return {
        name: [],
        is_active: [],
        is_default: [],
    };
}

function emptyVersionErrors() {
    return {
        recipe_type: [],
        output_quantity: [],
        status: [],
        lines: [],
    };
}

function emptyCreateForm() {
    return {
        item_id: "",
        recipe_type: "manufacturing",
        name: "",
        output_quantity: "",
        is_active: true,
        is_default: false,
    };
}

function emptyEditForm() {
    return {
        name: "",
        is_active: true,
        is_default: false,
    };
}

function emptyVersionForm() {
    return {
        recipe_type: "manufacturing",
        output_quantity: "1.000000",
    };
}

function normalizeErrors(errors, fallback) {
    if (!errors || typeof errors !== "object") {
        return fallback();
    }

    return {
        ...fallback(),
        ...errors,
    };
}

function showToast(message, tone = "success") {
    toast.value = { message, tone };

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }

    toastTimer.value = window.setTimeout(() => {
        toast.value = null;
        toastTimer.value = null;
    }, 1500);
}

function dismissToast() {
    toast.value = null;

    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
        toastTimer.value = null;
    }
}

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: "same-origin",
        ...options,
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken.value,
            ...(options.headers ?? {}),
        },
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message ?? "The request could not be completed.");
        error.payload = data;
        error.status = response.status;
        throw error;
    }

    return data;
}

async function fetchRecipes(nextSearch = search.value) {
    const listUrl = props.crudConfig.endpoints?.list;

    if (!listUrl) {
        listError.value = "Unable to load recipes.";
        return;
    }

    search.value = nextSearch;
    loading.value = true;
    listError.value = "";

    try {
        const url = new URL(listUrl, window.location.origin);
        url.searchParams.set("search", search.value);
        url.searchParams.set("sort", sort.value.column);
        url.searchParams.set("direction", sort.value.direction);

        const data = await jsonRequest(url.toString(), { method: "GET" });

        recipes.value = Array.isArray(data.data) ? data.data : [];
        if (data.meta?.sort?.column && data.meta?.sort?.direction) {
            sort.value = {
                column: data.meta.sort.column,
                direction: data.meta.sort.direction,
            };
        }
    } catch {
        listError.value = "Unable to load recipes.";
    } finally {
        loading.value = false;
    }
}

function findOutputItem(itemId) {
    return manufacturableItems.value.find((item) => String(item.id) === String(itemId)) ?? null;
}

function selectedOutputItemPrecision(itemId) {
    const outputItem = findOutputItem(itemId);

    return Number.isInteger(Number(outputItem?.uom_display_precision))
        ? Number(outputItem.uom_display_precision)
        : 6;
}

function normalizeQuantityValue(value, precision) {
    const normalizedPrecision = Math.max(0, Math.min(6, Number(precision ?? 6)));
    const normalizedValue = String(value ?? "").trim();

    if (!/^\d+(?:\.\d+)?$/.test(normalizedValue)) {
        return normalizedPrecision === 0 ? "1" : `1.${"".padEnd(normalizedPrecision, "0")}`;
    }

    const [wholePart, decimalPart = ""] = normalizedValue.split(".", 2);

    if (normalizedPrecision === 0) {
        return wholePart;
    }

    return `${wholePart}.${decimalPart.padEnd(normalizedPrecision, "0").slice(0, normalizedPrecision)}`;
}

function defaultQuantityForItem(itemId) {
    return normalizeQuantityValue("1", selectedOutputItemPrecision(itemId));
}

function filteredCreateItems() {
    return manufacturableItems.value.filter((item) => !createOnlyWithoutRecipe.value || !item.has_recipe);
}

function recipeTypeLabel(value) {
    return value === "fulfillment" ? "Fulfillment" : "Manufacturing";
}

function allRecipeTypeOptions() {
    return [
        { value: "manufacturing", label: "Manufacturing" },
        { value: "fulfillment", label: "Fulfillment" },
    ];
}

function recipeTypeOptionsForItem(itemId) {
    const outputItem = findOutputItem(itemId);

    if (!outputItem || !Array.isArray(outputItem.allowed_recipe_types) || outputItem.allowed_recipe_types.length === 0) {
        return allRecipeTypeOptions();
    }

    return outputItem.allowed_recipe_types.map((recipeType) => ({
        value: recipeType,
        label: recipeTypeLabel(recipeType),
    }));
}

function normalizeRecipeTypeSelection(selectedValue, allowedOptions) {
    const allowedValues = allowedOptions.map((option) => option.value);

    if (allowedValues.includes(String(selectedValue || ""))) {
        return String(selectedValue);
    }

    return allowedValues.includes("manufacturing") ? "manufacturing" : allowedValues[0] ?? "manufacturing";
}

function syncCreateRecipeType() {
    createForm.recipe_type = normalizeRecipeTypeSelection(createForm.recipe_type, recipeTypeOptionsForItem(createForm.item_id));

    if (createForm.recipe_type === "fulfillment") {
        createForm.output_quantity = "1.000000";
        return;
    }

    createForm.output_quantity = normalizeQuantityValue(
        createManufacturingOutputQuantity.value || defaultQuantityForItem(createForm.item_id),
        selectedOutputItemPrecision(createForm.item_id),
    );
    createManufacturingOutputQuantity.value = createForm.output_quantity;
}

function normalizeCreateOutputQuantity() {
    if (createForm.recipe_type === "fulfillment") {
        createForm.output_quantity = "1.000000";
        return;
    }

    createForm.output_quantity = normalizeQuantityValue(
        createForm.output_quantity,
        selectedOutputItemPrecision(createForm.item_id),
    );
    createManufacturingOutputQuantity.value = createForm.output_quantity;
}

function syncCreateNameFromSelectedItem() {
    createForm.name = findOutputItem(createForm.item_id)?.name ?? "";
}

function resetCreateForm(prefill = {}) {
    Object.assign(createForm, emptyCreateForm());
    createForm.item_id = prefill.item_id ? String(prefill.item_id) : "";
    createForm.output_quantity = defaultQuantityForItem(createForm.item_id);
    createManufacturingOutputQuantity.value = createForm.output_quantity;
    syncCreateRecipeType();
    syncCreateNameFromSelectedItem();
    createErrors.value = emptyCreateErrors();
    createError.value = "";
}

function openCreateDrawer(prefill = {}) {
    if (!permissions.value.showCreate) {
        return;
    }

    createOnlyWithoutRecipe.value = false;
    resetCreateForm(prefill);
    createDrawerOpen.value = true;
}

function closeCreateDrawer() {
    if (createSubmitting.value) {
        return;
    }

    createDrawerOpen.value = false;
    createErrors.value = emptyCreateErrors();
    createError.value = "";
}

async function submitCreate() {
    createSubmitting.value = true;
    createErrors.value = emptyCreateErrors();
    createError.value = "";

    try {
        await jsonRequest(props.crudConfig.endpoints?.create, {
            method: "POST",
            body: JSON.stringify({
                item_id: createForm.item_id ? Number(createForm.item_id) : "",
                recipe_type: createForm.recipe_type,
                name: createForm.name,
                output_quantity: createForm.output_quantity,
                is_active: createForm.is_active,
                is_default: createForm.is_default,
            }),
        });
        await fetchRecipes();
        createDrawerOpen.value = false;
        showToast("Recipe created.");
    } catch (error) {
        createErrors.value = normalizeErrors(error.payload?.errors, emptyCreateErrors);
        createError.value = error.payload?.message ?? "Validation failed.";
    } finally {
        createSubmitting.value = false;
    }
}

function openEdit(record) {
    editRecipeId.value = record.id;
    Object.assign(editForm, {
        name: record.name || "",
        is_active: record.version_status !== "ARCHIVED",
        is_default: Boolean(record.is_default),
    });
    editErrors.value = emptyEditErrors();
    editError.value = "";
    editDrawerOpen.value = true;
}

function closeEditDrawer() {
    if (editSubmitting.value) {
        return;
    }

    editDrawerOpen.value = false;
    editRecipeId.value = null;
    editErrors.value = emptyEditErrors();
    editError.value = "";
}

async function submitEdit() {
    editSubmitting.value = true;
    editErrors.value = emptyEditErrors();
    editError.value = "";

    try {
        await jsonRequest(String(props.crudConfig.endpoints?.update ?? "").replace("{id}", encodeURIComponent(String(editRecipeId.value))), {
            method: "PATCH",
            body: JSON.stringify(editForm),
        });
        await fetchRecipes();
        closeEditDrawer();
        showToast("Recipe updated.");
    } catch (error) {
        editErrors.value = normalizeErrors(error.payload?.errors, emptyEditErrors);
        editError.value = error.payload?.message ?? "Validation failed.";
    } finally {
        editSubmitting.value = false;
    }
}

function openVersion(record) {
    versionRecipeId.value = record.id;
    versionRecipeName.value = record.name || "";
    Object.assign(versionForm, {
        recipe_type: record.recipe_type || "manufacturing",
        output_quantity: record.output_quantity || "1.000000",
    });
    versionErrors.value = emptyVersionErrors();
    versionError.value = "";
    versionDrawerOpen.value = true;
}

function closeVersionDrawer() {
    if (versionSubmitting.value) {
        return;
    }

    versionDrawerOpen.value = false;
    versionRecipeId.value = null;
    versionRecipeName.value = "";
    versionErrors.value = emptyVersionErrors();
    versionError.value = "";
}

async function submitVersion() {
    versionSubmitting.value = true;
    versionErrors.value = emptyVersionErrors();
    versionError.value = "";

    try {
        await jsonRequest(String(props.crudConfig.endpoints?.versionStore ?? "").replace("{id}", encodeURIComponent(String(versionRecipeId.value))), {
            method: "POST",
            body: JSON.stringify(versionForm),
        });
        await fetchRecipes();
        closeVersionDrawer();
        showToast("Recipe version created.");
    } catch (error) {
        versionErrors.value = normalizeErrors(error.payload?.errors, emptyVersionErrors);
        versionError.value = error.payload?.message ?? "Validation failed.";
    } finally {
        versionSubmitting.value = false;
    }
}

function openArchive(record) {
    archiveRecipeId.value = record.id;
    archiveRecipeName.value = record.name || "";
    archiveError.value = "";
    archiveDrawerOpen.value = true;
}

function closeArchiveDrawer() {
    if (archiveSubmitting.value) {
        return;
    }

    archiveDrawerOpen.value = false;
    archiveRecipeId.value = null;
    archiveRecipeName.value = "";
    archiveError.value = "";
}

async function submitArchive() {
    archiveSubmitting.value = true;
    archiveError.value = "";

    try {
        await jsonRequest(String(props.crudConfig.endpoints?.delete ?? "").replace("{id}", encodeURIComponent(String(archiveRecipeId.value))), {
            method: "DELETE",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken.value,
            },
        });
        await fetchRecipes();
        closeArchiveDrawer();
        showToast("Recipe archived.");
    } catch (error) {
        archiveError.value = error.payload?.message ?? "Unable to archive recipe.";
    } finally {
        archiveSubmitting.value = false;
    }
}

async function makeOrder(record) {
    if (!record?.make_url) {
        return;
    }

    try {
        await jsonRequest(record.make_url, {
            method: "POST",
            body: JSON.stringify({
                runs: "1.000000",
            }),
        });
        await fetchRecipes();
        showToast("Make order created.");
    } catch {
        showToast("Unable to create make order.", "error");
    }
}

function handleAction({ action, record }) {
    if (action.id === "make") {
        void makeOrder(record);
        return;
    }

    if (action.id === "edit") {
        openEdit(record);
        return;
    }

    if (action.id === "archive") {
        openArchive(record);
    }
}

function recipeTitleBadges(record) {
    const badges = [];
    const type = String(record?.recipe_type_label || record?.recipe_type || "").trim();
    const version = String(record?.current_version_number_display || "").trim();

    if (type !== "") {
        badges.push({
            label: type,
            tone: type.toLowerCase() === "fulfillment" ? "green" : "blue",
        });
    }

    if (version !== "" && version !== "-") {
        badges.push({
            label: version,
            tone: "gray",
        });
    }

    return badges;
}

function recipeSummary(record) {
    const parts = [];

    if (record?.output_quantity_display || record?.output_quantity) {
        parts.push(`Qty ${record.output_quantity_display || record.output_quantity}`);
    }

    parts.push(record?.updated_at || "-");

    return parts.join(" · ");
}

function recipeDetailRows(record) {
    return [
        {
            left: [
                { label: "Output", value: record?.output_item_name || "-" },
            ],
            right: [
                { label: "Qty", value: record?.output_quantity_display || record?.output_quantity || "-" },
            ],
        },
        {
            left: [
                { label: "Updated", value: record?.updated_at || "-" },
            ],
            right: [],
        },
    ];
}

onMounted(() => {
    void fetchRecipes();

    const prefillCreate = props.payload.prefill_create ?? {};
    if (prefillCreate.open === true) {
        openCreateDrawer({ item_id: prefillCreate.item_id });
    }
});

onBeforeUnmount(() => {
    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
    }
});
</script>

<template>
    <Head title="Recipes" />

    <AuthShell :shell="shell" title="Recipes">
        <div class="flex h-full min-h-0 w-full">
            <div class="relative flex min-h-0 w-full flex-1">
                <UiToast
                    :visible="Boolean(toast)"
                    :type="toast?.tone || 'success'"
                    :message="toast?.message || ''"
                    @dismiss="dismissToast"
                />

                <ResourceIndex
                    v-model:search="search"
                    :labels="labels"
                    :permissions="permissions"
                    :records="recipes"
                    :loading="loading"
                    :error="listError"
                    bounded-height-class="h-full"
                    @search="fetchRecipes"
                    @create="openCreateDrawer"
                >
                    <template #default="{ records }">
                        <ResourceCardGrid
                            :records="records"
                            :labels="labels"
                            :loading="loading"
                            :actions="actions"
                            :title="(record) => record.name || '-'"
                            :title-badges="recipeTitleBadges"
                            :subtitle="(record) => record.output_item_name || '-'"
                            :mobile-subtitle="recipeSummary"
                            :detail-rows="recipeDetailRows"
                            :href="(record) => record.show_url"
                            @action="handleAction"
                        >
                            <template #desktop-aside="{ record }">
                                <button
                                    v-if="record.available_actions?.includes('edit')"
                                    type="button"
                                    class="shrink-0 cursor-pointer rounded-md border border-gray-200 px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-600 transition hover:bg-gray-50"
                                    @click.stop="openVersion(record)"
                                >
                                    Version
                                </button>
                            </template>
                            <template #mobile-aside="{ record }">
                                <button
                                    v-if="record.available_actions?.includes('edit')"
                                    type="button"
                                    class="shrink-0 cursor-pointer rounded-md border border-gray-200 px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-600 transition hover:bg-gray-50"
                                    @click.stop="openVersion(record)"
                                >
                                    Version
                                </button>
                            </template>
                        </ResourceCardGrid>
                    </template>
                </ResourceIndex>
            </div>
        </div>

        <ResourceCreateDrawer :open="createDrawerOpen" title="Create Recipe" description="Create a recipe and its first draft version." submit-label="Create" :submitting="createSubmitting" :error="createError" panel-class="w-screen max-w-md" @close="closeCreateDrawer" @submit="submitCreate">
            <div class="space-y-4">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Output Item</span>
                    <select v-model="createForm.item_id" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]" @change="syncCreateRecipeType(); syncCreateNameFromSelectedItem()">
                        <option value="">Select output item</option>
                        <option v-for="item in filteredCreateItems()" :key="item.id" :value="String(item.id)">{{ item.name }} {{ item.uom_display }}</option>
                    </select>
                    <span v-if="createErrors.item_id[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.item_id[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Recipe Type</span>
                    <select v-model="createForm.recipe_type" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]" @change="syncCreateRecipeType">
                        <option v-for="option in recipeTypeOptionsForItem(createForm.item_id)" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <span v-if="createErrors.recipe_type[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.recipe_type[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Name</span>
                    <input v-model="createForm.name" type="text" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                    <span v-if="createErrors.name[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.name[0] }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Output Quantity</span>
                    <input v-model="createForm.output_quantity" type="text" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]" :disabled="createForm.recipe_type === 'fulfillment'" @blur="normalizeCreateOutputQuantity">
                    <span v-if="createErrors.output_quantity[0]" class="mt-1 block text-xs text-red-600">{{ createErrors.output_quantity[0] }}</span>
                </label>

                <label class="flex items-center justify-between gap-3 text-xs font-semibold text-gray-700">
                    Active
                    <input v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300 text-[#6f895d] focus:ring-[#6f895d]">
                </label>

                <label class="flex items-center justify-between gap-3 text-xs font-semibold text-gray-700">
                    Default
                    <input v-model="createForm.is_default" type="checkbox" class="rounded border-gray-300 text-[#6f895d] focus:ring-[#6f895d]">
                </label>
            </div>
        </ResourceCreateDrawer>

        <ResourceCreateDrawer :open="editDrawerOpen" title="Edit Recipe" submit-label="Save" :submitting="editSubmitting" :error="editError" panel-class="w-screen max-w-md" @close="closeEditDrawer" @submit="submitEdit">
            <div class="space-y-4">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Name</span>
                    <input v-model="editForm.name" type="text" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                    <span v-if="editErrors.name[0]" class="mt-1 block text-xs text-red-600">{{ editErrors.name[0] }}</span>
                </label>
                <label class="flex items-center justify-between gap-3 text-xs font-semibold text-gray-700">
                    Active
                    <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300 text-[#6f895d] focus:ring-[#6f895d]">
                </label>
                <label class="flex items-center justify-between gap-3 text-xs font-semibold text-gray-700">
                    Default
                    <input v-model="editForm.is_default" type="checkbox" class="rounded border-gray-300 text-[#6f895d] focus:ring-[#6f895d]">
                </label>
            </div>
        </ResourceCreateDrawer>

        <ResourceCreateDrawer :open="versionDrawerOpen" :title="`Create Version${versionRecipeName ? `: ${versionRecipeName}` : ''}`" submit-label="Create Version" :submitting="versionSubmitting" :error="versionError" panel-class="w-screen max-w-md" @close="closeVersionDrawer" @submit="submitVersion">
            <div class="space-y-4">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Recipe Type</span>
                    <select v-model="versionForm.recipe_type" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                        <option v-for="option in allRecipeTypeOptions()" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <span v-if="versionErrors.recipe_type[0]" class="mt-1 block text-xs text-red-600">{{ versionErrors.recipe_type[0] }}</span>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-gray-700">Output Quantity</span>
                    <input v-model="versionForm.output_quantity" type="text" class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#6f895d] focus:ring-[#6f895d]">
                    <span v-if="versionErrors.output_quantity[0]" class="mt-1 block text-xs text-red-600">{{ versionErrors.output_quantity[0] }}</span>
                </label>
            </div>
        </ResourceCreateDrawer>

        <ResourceCreateDrawer :open="archiveDrawerOpen" title="Archive Recipe" submit-label="Archive" :submitting="archiveSubmitting" :error="archiveError" panel-class="w-screen max-w-sm" @close="closeArchiveDrawer" @submit="submitArchive">
            <p class="text-sm text-gray-700">Archive {{ archiveRecipeName || "this recipe" }}?</p>
        </ResourceCreateDrawer>
    </AuthShell>
</template>
