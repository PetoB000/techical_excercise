<script setup>
import { useQuery } from '../composables/useGraphql';
import UserTable from '../components/UserTable.vue';

const USERS = `
  query Users {
    users {
      id
      hrId
      firstName
      lastName
      email
      department
      isActive
    }
  }
`;

const { data, loading, error } = useQuery(USERS);

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
    <UserTable v-else :users="data ? data.users : []" @updated="onUserUpdated" />
  </section>
</template>
