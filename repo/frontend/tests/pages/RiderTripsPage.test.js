import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { getMock, postMock, pushMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn(),
  pushMock: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}))

vi.mock('@/stores/authStore', () => ({
  useAuthStore: () => ({
    user: { id: 5, username: 'rider01', role: 'rider' },
    logout: vi.fn(),
  }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
    post: postMock,
  },
}))

import RiderTripsPage from '@/pages/rider/RiderTripsPage.vue'

// Stubs that support v-model so form fields bind correctly
const InputStub = {
  template: '<div><label>{{ label }}</label><input :type="type || \'text\'" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
  props: ['modelValue', 'label', 'type', 'placeholder', 'icon'],
  emits: ['update:modelValue'],
}

const ButtonStub = {
  template: '<button :type="type || \'button\'" :disabled="loading"><slot /></button>',
  props: ['type', 'loading', 'disabled'],
}

const TripCardStub = {
  props: ['order'],
  template: '<article class="trip-card">{{ order.status }}</article>',
}

const mountPage = () =>
  mount(RiderTripsPage, {
    global: {
      stubs: {
        AppShell: { template: '<div><slot /></div>' },
        Input: InputStub,
        Button: ButtonStub,
        TripCard: TripCardStub,
        Teleport: { template: '<div><slot /></div>' },
      },
    },
  })

const futureDate = () => {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  return d.toISOString().split('T')[0]
}

const pastDate = () => {
  const d = new Date()
  d.setDate(d.getDate() - 1)
  return d.toISOString().split('T')[0]
}

const openModal = async (wrapper) => {
  await wrapper.find('.fab').trigger('click')
}

const fillValidForm = async (wrapper, date) => {
  const inputs = wrapper.findAll('input')
  await inputs[0].setValue('123 Main St')
  await inputs[1].setValue('456 Oak Ave')
  await inputs[2].setValue(date)
  await inputs[3].setValue('10:00')
  await inputs[4].setValue('14:00')
}

describe('RiderTripsPage', () => {
  const allOrders = [
    {
      id: 11,
      status: "matching",
      origin_address: "A",
      destination_address: "B",
    },
    {
      id: 12,
      status: "exception",
      origin_address: "C",
      destination_address: "D",
    },
    {
      id: 13,
      status: "completed",
      origin_address: "E",
      destination_address: "F",
    },
  ];

  beforeEach(() => {
    vi.clearAllMocks();
    getMock.mockImplementation((_url, config = {}) => {
      const status = config.params?.status;
      const data = status
        ? allOrders.filter((o) => o.status === status)
        : allOrders;
      return Promise.resolve({ data: { data } });
    });
  });

  // ── tab filtering ──────────────────────────────────────────────────────────

  it("shows exception status tab and filters rider trips by exception", async () => {
    const wrapper = mountPage();
    await flushPromises();

    const exceptionTab = wrapper
      .findAll(".tabs button")
      .find((b) => b.text() === "exception");
    expect(exceptionTab).toBeTruthy();

    await exceptionTab.trigger("click");
    await flushPromises();

    expect(getMock).toHaveBeenCalledWith("/ride-orders", {
      params: { status: "exception", per_page: 30 },
    });

    const cards = wrapper.findAll(".trip-card");
    expect(cards).toHaveLength(1);
    expect(cards[0].text()).toContain("exception");
  });

  it("shows empty state message when no trips in current tab", async () => {
    getMock.mockResolvedValue({ data: { data: [] } });
    const wrapper = mountPage();
    await flushPromises();
    expect(wrapper.text()).toContain("No trips in this status yet.");
  });

  it("renders a TripCard for each order returned by the API", async () => {
    const wrapper = mountPage();
    await flushPromises();
    expect(wrapper.findAll(".trip-card").length).toBe(3);
  });

  // ── create modal ───────────────────────────────────────────────────────────

  it("opens the create trip modal when the + New Trip button is clicked", async () => {
    const wrapper = mountPage();
    await flushPromises();
    expect(wrapper.text()).not.toContain("New Trip Request");
    await openModal(wrapper);
    expect(wrapper.text()).toContain("New Trip Request");
  });

  // ── form validation ────────────────────────────────────────────────────────

  it("requires origin address — shows error and does not POST", async () => {
    const wrapper = mountPage();
    await flushPromises();
    await openModal(wrapper);
    await wrapper.find("form").trigger("submit");

    expect(wrapper.text()).toContain("Origin and destination are required.");
    expect(postMock).not.toHaveBeenCalled();
  });

  it("requires destination address — shows error when destination is blank", async () => {
    const wrapper = mountPage();
    await flushPromises();
    await openModal(wrapper);

    const inputs = wrapper.findAll("input");
    await inputs[0].setValue("123 Main St");
    // destination intentionally left blank

    await wrapper.find("form").trigger("submit");

    expect(wrapper.text()).toContain("Origin and destination are required.");
    expect(postMock).not.toHaveBeenCalled();
  });

  // ── rider count stepper ────────────────────────────────────────────────────

  it("increments rider count with the + button", async () => {
    const wrapper = mountPage();
    await flushPromises();
    await openModal(wrapper);

    expect(wrapper.find(".stepper strong").text()).toBe("1");
    await wrapper.findAll(".stepper button")[1].trigger("click"); // +
    expect(wrapper.find(".stepper strong").text()).toBe("2");
  });

  it("decrements rider count with the - button and clamps at 1", async () => {
    const wrapper = mountPage();
    await flushPromises();
    await openModal(wrapper);

    await wrapper.findAll(".stepper button")[1].trigger("click"); // → 2
    await wrapper.findAll(".stepper button")[0].trigger("click"); // → 1
    await wrapper.findAll(".stepper button")[0].trigger("click"); // → still 1
    expect(wrapper.find(".stepper strong").text()).toBe("1");
  });
})
