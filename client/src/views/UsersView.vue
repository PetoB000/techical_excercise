<script setup>
import { ref, computed, watch } from 'vue';
import { useQuery } from '../composables/useGraphql';
import UserTable from '../components/UserTable.vue';

const PAGE_SIZE = 100;

const USERS = `
  query Users($limit: Int!, $offset: Int!) {
    users(limit: $limit, offset: $offset) {
      id
      hrId
      firstName
      lastName
      email
      department
      isActive
    }
    usersCount
  }
`;

const page = ref(0);
const offset = computed(() => page.value * PAGE_SIZE);

const { data, loading, error, refetch } = useQuery(USERS, {
  limit: PAGE_SIZE,
  offset: offset.value,
});

const totalPages = computed(() => {
  if (!data.value) return 1;
  return Math.max(1, Math.ceil(data.value.usersCount / PAGE_SIZE));
});

watch(page, (newPage) => {
  refetch({ limit: PAGE_SIZE, offset: newPage * PAGE_SIZE });
});

function onUserUpdated(updatedUser) {
  if (!data.value) return;
  const index = data.value.users.findIndex((u) => u.id === updatedUser.id);
  if (index !== -1) {
    data.value.users[index] = updatedUser;
  }
}
</script>

<template>
  <section>
    <h2>Staff users</h2>
    <p v-if="loading">Loading…</p>
    <p v-else-if="error" class="error">{{ error.message }}</p>
    <template v-else>
      <UserTable :users="data ? data.users : []" @updated="onUserUpdated" />

      <div v-if="data && data.usersCount > PAGE_SIZE" class="pagination">
        <button :disabled="page === 0" @click="page--">← Previous</button>
        <span>Page {{ page + 1 }} of {{ totalPages }} ({{ data.usersCount }} users)</span>
        <button :disabled="page + 1 >= totalPages" @click="page++">Next →</button>
      </div>
    </template>
  </section>
</template>
