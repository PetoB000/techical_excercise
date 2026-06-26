<script setup>
import { ref } from 'vue';
import { useMutation } from '../composables/useGraphql';
import ResultsSummary from '../components/ResultsSummary.vue';

const IMPORT_CSV = `
  mutation ImportCsv($filename: String!, $contentBase64: String!) {
    importCsv(filename: $filename, contentBase64: $contentBase64) {
      rowsRead
      created
      updated
      skipped
      skippedRows {
        line
        errors
      }
    }
  }
`;

const CHUNK_SIZE = 5000;

const { mutate } = useMutation(IMPORT_CSV);

const loading   = ref(false);
const error     = ref(null);
const summary   = ref(null);
const filename  = ref('');
const progress  = ref('');

function toBase64(str) {
  return btoa(unescape(encodeURIComponent(str)));
}

async function onFileChange(event) {
  const file = event.target.files[0];
  if (!file) return;

  filename.value = file.name;
  summary.value  = null;
  error.value    = null;
  loading.value  = true;
  progress.value = '';

  try {
    const text      = await file.text();
    const lines     = text.split('\n');
    const header    = lines[0];
    const dataLines = lines.slice(1).filter((l) => l.trim() !== '');

    const totalChunks = Math.max(1, Math.ceil(dataLines.length / CHUNK_SIZE));
    const accumulated = { rowsRead: 0, created: 0, updated: 0, skipped: 0, skippedRows: [] };

    for (let i = 0; i < dataLines.length; i += CHUNK_SIZE) {
      const chunkIndex = i / CHUNK_SIZE;
      progress.value = `Importing chunk ${chunkIndex + 1} of ${totalChunks}…`;

      const chunkLines  = dataLines.slice(i, i + CHUNK_SIZE);
      const csvChunk    = [header, ...chunkLines].join('\n') + '\n';
      const data        = await mutate({ filename: file.name, contentBase64: toBase64(csvChunk) });
      const chunkResult = data.importCsv;

      accumulated.rowsRead += chunkResult.rowsRead;
      accumulated.created  += chunkResult.created;
      accumulated.updated  += chunkResult.updated;
      accumulated.skipped  += chunkResult.skipped;


      const lineOffset = i; // chunk k starts at data row k * CHUNK_SIZE → offset = i
      accumulated.skippedRows.push(
        ...chunkResult.skippedRows.map((r) => ({ ...r, line: r.line + lineOffset })),
      );
    }

    summary.value = accumulated;
  } catch (e) {
    error.value = e;
  } finally {
    loading.value  = false;
    progress.value = '';
  }
}
</script>

<template>
  <section>
    <h2>Import a staff CSV</h2>
    <p>Select an HR export to import staff into the user store.</p>
    <input type="file" accept=".csv,text/csv" @change="onFileChange" :disabled="loading" />

    <p v-if="loading">{{ progress || `Importing ${filename}…` }}</p>
    <p v-if="error" class="error">Import failed: {{ error.message }}</p>

    <ResultsSummary v-if="summary" :summary="summary" />
  </section>
</template>
