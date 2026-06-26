import { ref } from 'vue';

const ENDPOINT = '/graphql';

/**
 * Send a single GraphQL operation and return its `data`, throwing on errors.
 *
 * @param {string} query
 * @param {object} [variables]
 * @returns {Promise<object>}
 */
export async function request(query, variables = {}) {
  const response = await fetch(ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ query, variables }),
  });

  const result = await response.json();
  if (result.errors && result.errors.length) {
    throw new Error(result.errors[0].message);
  }

  return result.data;
}

/**
 * Run a query immediately and expose reactive state. Mirrors the
 * useQuery/useMutation shape used on the CCL project.
 */
export function useQuery(query, variables = {}) {
  const data = ref(null);
  const loading = ref(false);
  const error = ref(null);

  async function refetch(newVariables) {
    loading.value = true;
    error.value = null;
    try {
      data.value = await request(query, newVariables ?? variables);
    } catch (e) {
      error.value = e;
    } finally {
      loading.value = false;
    }
  }

  refetch();

  return { data, loading, error, refetch };
}

/**
 * Return a `mutate(variables)` function plus reactive loading/error state.
 */
export function useMutation(mutation) {
  const loading = ref(false);
  const error = ref(null);

  async function mutate(variables = {}) {
    loading.value = true;
    error.value = null;
    try {
      return await request(mutation, variables);
    } catch (e) {
      error.value = e;
      throw e;
    } finally {
      loading.value = false;
    }
  }

  return { mutate, loading, error };
}
