import { describe, it, expect, vi } from 'vitest';
import { ref } from 'vue';
import { mount } from '@vue/test-utils';
import UserTable from '../UserTable.vue';

vi.mock('../../composables/useGraphql', () => ({
  useMutation: () => ({
    mutate: vi.fn(),
    loading: ref(false),
    error: ref(null),
  }),
}));

describe('UserTable', () => {
  const users = [
    {
      id: 1,
      hrId: 'E1001',
      firstName: 'Alice',
      lastName: 'Adams',
      email: 'alice@example.com',
      department: 'Engineering',
      isActive: true,
    },
    {
      id: 2,
      hrId: 'E1002',
      firstName: 'Bob',
      lastName: 'Brown',
      email: 'bob@example.com',
      department: 'Sales',
      isActive: false,
    },
  ];

  it('renders a row per user with their details', () => {
    const wrapper = mount(UserTable, { props: { users } });

    const rows = wrapper.findAll('tbody tr');
    expect(rows).toHaveLength(2);
    expect(rows[0].text()).toContain('E1001');
    expect(rows[0].text()).toContain('Alice');
    expect(rows[0].text()).toContain('Yes');
    expect(rows[1].text()).toContain('E1002');
    expect(rows[1].text()).toContain('Bob');
    expect(rows[1].text()).toContain('No');
  });

  it('shows a placeholder when there are no users', () => {
    const wrapper = mount(UserTable, { props: { users: [] } });

    expect(wrapper.text()).toContain('No users imported yet.');
  });

  it('renders an Edit button for each user', () => {
    const wrapper = mount(UserTable, { props: { users } });

    const buttons = wrapper.findAll('button').filter((b) => b.text() === 'Edit');
    expect(buttons).toHaveLength(2);
  });

  it('switches the row to an inline form when Edit is clicked', async () => {
    const wrapper = mount(UserTable, { props: { users } });

    await wrapper.findAll('button')[0].trigger('click'); // first Edit button

    expect(wrapper.find('input').exists()).toBe(true);
    expect(wrapper.text()).toContain('Save');
    expect(wrapper.text()).toContain('Cancel');
  });

  it('restores the view row when Cancel is clicked', async () => {
    const wrapper = mount(UserTable, { props: { users } });

    await wrapper.findAll('button')[0].trigger('click'); // Edit
    expect(wrapper.find('input').exists()).toBe(true);

    const cancelBtn = wrapper.findAll('button').find((b) => b.text() === 'Cancel');
    await cancelBtn.trigger('click');

    expect(wrapper.find('input').exists()).toBe(false);
  });
});
