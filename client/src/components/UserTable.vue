<script setup>
import { ref } from 'vue';
import { useMutation } from '../composables/useGraphql';

const props = defineProps({
  users: {
    type: Array,
    required: true,
  },
});

const emit = defineEmits(['updated']);

const UPDATE_USER = `
  mutation UpdateUser($id: Int!, $input: UpdateUserInput!) {
    updateUser(id: $id, input: $input) {
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

const { mutate, loading: saving } = useMutation(UPDATE_USER);

const editingId = ref(null);
const editForm = ref({});
const saveError = ref(null);

function startEdit(user) {
  editingId.value = user.id;
  editForm.value = {
    firstName:  user.firstName,
    lastName:   user.lastName,
    email:      user.email,
    department: user.department,
    isActive:   user.isActive,
  };
  saveError.value = null;
}

function cancelEdit() {
  editingId.value = null;
  saveError.value = null;
}

async function saveEdit(userId) {
  saveError.value = null;
  try {
    const updated = await mutate({
      id: userId,
      input: {
        firstName:  editForm.value.firstName,
        lastName:   editForm.value.lastName,
        email:      editForm.value.email,
        department: editForm.value.department,
        isActive:   editForm.value.isActive,
      },
    });
    editingId.value = null;
    emit('updated', updated.updateUser);
  } catch (e) {
    saveError.value = e.message;
  }
}
</script>

<template>
  <table class="users">
    <thead>
      <tr>
        <th>HR ID</th>
        <th>First name</th>
        <th>Last name</th>
        <th>Email</th>
        <th>Department</th>
        <th>Active</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <template v-for="user in users" :key="user.id">
        <!-- view row -->
        <tr v-if="editingId !== user.id">
          <td>{{ user.hrId }}</td>
          <td>{{ user.firstName }}</td>
          <td>{{ user.lastName }}</td>
          <td>{{ user.email }}</td>
          <td>{{ user.department }}</td>
          <td>{{ user.isActive ? 'Yes' : 'No' }}</td>
          <td><button @click="startEdit(user)">Edit</button></td>
        </tr>
        <!-- inline edit row -->
        <tr v-else class="editing">
          <td>{{ user.hrId }}</td>
          <td><input v-model="editForm.firstName" /></td>
          <td><input v-model="editForm.lastName" /></td>
          <td><input v-model="editForm.email" type="email" /></td>
          <td><input v-model="editForm.department" /></td>
          <td>
            <input type="checkbox" v-model="editForm.isActive" />
          </td>
          <td>
            <button @click="saveEdit(user.id)" :disabled="saving">Save</button>
            <button @click="cancelEdit" :disabled="saving">Cancel</button>
          </td>
        </tr>
        <tr v-if="editingId === user.id && saveError">
          <td colspan="7" class="error">{{ saveError }}</td>
        </tr>
      </template>
      <tr v-if="!users.length">
        <td colspan="7">No users imported yet.</td>
      </tr>
    </tbody>
  </table>
</template>
